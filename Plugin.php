<?php
/**
 * 评论回复通过 MailGun 发送邮件提醒
 *
 * @package CommentMailPlus
 * @author oott123
 * @version 0.0.3
 * @link http://oott123.com
 */
class CommentMailPlus_Plugin implements Typecho_Plugin_Interface {
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        if (!function_exists('curl_init')) {
            throw new Typecho_Plugin_Exception(_t('检测到当前 PHP 环境没有 curl 组件, 无法正常使用此插件'));
        }
        Helper::addAction('comment-mail-plus', 'CommentMailPlus_Action');
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('CommentMailPlus_Plugin', 'toMail');
        return _t('请到设置面板正确配置 MailGun 才可正常工作。');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
        Helper::removeAction('comment-mail-plus');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, NULL,
                _t('收件人邮箱'),_t('接收邮件用的信箱，为空则使用文章作者个人设置中的邮箱！'));
        $form->addInput($mail->addRule('email', _t('请填写正确的邮箱！')));

        $status = new Typecho_Widget_Helper_Form_Element_Checkbox('status',
                array('approved' => '提醒已通过评论',
                        'waiting' => '提醒待审核评论',
                        'spam' => '提醒垃圾评论'),
                array('approved', 'waiting'), '提醒设置',_t('该选项仅针对博主，访客只发送已通过的评论。'));
        $form->addInput($status);

        $other = new Typecho_Widget_Helper_Form_Element_Checkbox('other',
                array('to_owner' => '有评论及回复时，发邮件通知博主',
                    'to_guest' => '评论被回复时，发邮件通知评论者',
                    'to_me'=>'自己回复自己的评论时（同时针对博主和访客），发邮件通知',
                    'to_log' => '记录邮件发送日志'),
                array('to_owner','to_guest'), '其他设置',_t('如果勾选“记录邮件发送日志”选项，则会在 ./CommentMailPlus/logs/mail_log.php 中记录发送信息。<br />
                    关键性错误日志将自动记录到 ./CommentMailPlus/logs/error_log.php 中。<br />
                    '));
        $form->addInput($other->multiMode());
        $key = new Typecho_Widget_Helper_Form_Element_Text('key', NULL, 'xxxxxxxxxxxxxxxxxxx-xxxxxx-xxxxxx',
                _t('MailGun API 密钥'), _t('请填写在<a href="https://mailgun.com/"> MailGun </a>申请的密钥，可在<a href="https://app.mailgun.com/app/account/security/api_keys">个人页</a>中查看 '));
        $form->addInput($key->addRule('required', _t('密钥不能为空')));
        $domain = new Typecho_Widget_Helper_Form_Element_Text('domain', NULL, 'samples.mailgun.org',
                _t('MailGun 域名'), _t('请填写您的邮件域名，若使用官方提供的测试域名可能存在其他问题'));
        $form->addInput($domain->addRule('required', _t('邮件域名不能为空')));
        $mailAddress = new Typecho_Widget_Helper_Form_Element_Text('mailAddress', NULL, 'no-reply@samples.mailgun.org',
                _t('发件人邮箱'));
        $form->addInput($mailAddress->addRule('required', _t('发件人地址不能为空')));
        $senderName = new Typecho_Widget_Helper_Form_Element_Text('senderName', NULL, '评论提醒',
                _t('发件人显示名'));
        $form->addInput($senderName);

        $titleForOwner = new Typecho_Widget_Helper_Form_Element_Text('titleForOwner',null,"[{site}]:《{title}》有新的评论",
                _t('博主接收邮件标题'));
        $form->addInput($titleForOwner);

        $titleForGuest = new Typecho_Widget_Helper_Form_Element_Text('titleForGuest',null,"[{site}]:您在《{title}》的评论有了回复",
                _t('访客接收邮件标题'));
        $form->addInput($titleForGuest);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {

    }

    /**
     * 组合邮件内容
     *
     * @access public
     * @param $post 调用参数
     * @return void
     */
    public static function toMail($post) {
        //发送邮件
        $settings=Helper::options()->plugin('CommentMailPlus');
        $options = Typecho_Widget::widget('Widget_Options');
        //邮件模板变量
        $tempinfo['site']      = $options->title;
        $tempinfo['siteUrl']   = $options->siteUrl;
        $tempinfo['title']     = $post->title;
        $tempinfo['cid']       = $post->cid;
        $tempinfo['coid']      = $post->coid;
        $tempinfo['created']   = $post->created;
        $tempinfo['timezone']  = $options->timezone;
        $tempinfo['author']    = $post->author;
        $tempinfo['authorId']  = $post->authorId;
        $tempinfo['ownerId']   = $post->ownerId;
        $tempinfo['mail']      = $post->mail;
        $tempinfo['ip']        = $post->ip;
        $tempinfo['title']     = $post->title;
        $tempinfo['text']      = $post->text;
        $tempinfo['permalink'] = $post->permalink;
        $tempinfo['status']    = $post->status;
        $tempinfo['parent']    = $post->parent;
        $tempinfo['manage']    = $options->siteUrl."admin/manage-comments.php";
        $_db = Typecho_Db::get();
        $original = $_db->fetchRow($_db::get()->select('author', 'mail', 'text')
                    ->from('table.comments')
                    ->where('coid = ?', $tempinfo['parent']));
        //var_dump($original);die();
        $tempinfo['originalMail'] = $original['mail'];
        $tempinfo['originalText'] = $original['text'];
        $tempinfo['originalAuthor'] = $original['author'];

        //判断发送
        //1.发送博主邮件
        if(in_array('to_owner', $settings->other) && in_array($tempinfo['status'], $settings->status)){
            $this_mail = $tempinfo['mail'];
            $to_mail = $settings->mail;
            if(!$to_mail){
                Typecho_Widget::widget('Widget_Users_Author@' . $tempinfo['cid'], array('uid' => $tempinfo['authorId']))->to($user);
                $to_mail = $user->mail;
            }
            if($this_mail != $to_mail || in_array('to_me',$settings->other)){
                //判定可以发送邮件
                $from_mail = $settings->mailAddress;
                $title = self::_getTitle(false,$settings,$tempinfo);
                $body = self::_getHtml(false,$tempinfo);
                self::_sendMail($to_mail,$from_mail,$title,$body,$settings);
            }
        }
        //2.发送评论者邮件
        if(in_array('to_guest', $settings->other) && 'approved'==$tempinfo['status'] && $tempinfo['originalMail']){
            $to_mail = $tempinfo['originalMail'];
            $from_mail = $settings->mailAddress;
            $title = self::_getTitle(true,$settings,$tempinfo);
            $body = self::_getHtml(true,$tempinfo);
            self::_sendMail($to_mail,$from_mail,$title,$body,$settings);
        }
    }
    public static function _getTitle($toGuest,$settings,$tempinfo){
        //获取发送标题
        $title = '';
        if($toGuest){
            $title = $settings->titleForGuest;
        }else{
            $title = $title = $settings->titleForOwner;
        }
        return str_replace(array('{title}','{site}'), array($tempinfo['title'],$tempinfo['site']), $title);
    }
    public static function _getHtml($toGuest,$tempinfo){
        //获取发送模板
        $dir = dirname(__FILE__).'/';
        $time = date("Y-m-d H:i:s",$tempinfo['created']+$tempinfo['timezone']);
        $search=$replace=array();
        if($toGuest){
            $dir.='guest.html';
            $search = array('{site}','{siteUrl}', '{title}','{author_p}','{author}','{mail}','{permalink}','{text}','{text_p}','{time}');
            $replace = array($tempinfo['site'],$tempinfo['siteUrl'],$tempinfo['title'],$tempinfo['originalAuthor'],$tempinfo['author'], $tempinfo['mail'],$tempinfo['permalink'],$tempinfo['text'],$tempinfo['originalText'],$time);
        }else{
            $dir.='owner.html';
            $status = array(
                "approved" => '通过',
                "waiting"  => '待审',
                "spam"     => '垃圾'
            );
            $search = array('{site}','{siteUrl}', '{title}','{author}','{ip}','{mail}','{permalink}','{manage}','{text}','{time}','{status}');
            $replace = array($tempinfo['site'],$tempinfo['siteUrl'],$tempinfo['title'],$tempinfo['author'],$tempinfo['ip'],$tempinfo['mail'],$tempinfo['permalink'],$tempinfo['manage'],$tempinfo['text'],$time,$status[$tempinfo['status']]);
        }
        $html = file_get_contents($dir);
        return str_replace($search, $replace, $html);
    }
    public static function _sendMail($to_mail,$from_mail,$title,$body,$settings){
        //发送邮件
        //self::_log($to_mail,'debug');return;
        $api_key = $settings->key;
        $domain = $settings->domain;
        $from_mail = $settings->senderName.' <'.$from_mail.'>';
        $postData = array(
            'from' => $from_mail,
            'to' => $to_mail,
            'subject' => $title,
            'html' => $body,
            );
        $url = 'https://api.mailgun.net/v3/'.$domain.'/messages';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERPWD,'api:'.$api_key);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HEADER, true);
        self::_log('curl prepareing...'.print_r(curl_getinfo($ch),1),'debug');
        $result = curl_exec($ch);
        self::_log('API return...'.$result,'debug');
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $result = substr($result, $headerSize);
        $res = json_decode($result,1);
        self::_log('curl excuted...'.print_r(curl_getinfo($ch),1),'debug');
        self::_log($to_mail.'邮件发送：'.$res['message']);
    }
    public static function _log($msg,$file='error'){
        //记录日志
        $settings=Helper::options()->plugin('CommentMailPlus');
        if(!in_array('to_log', $settings->other)) return false;
        //开发者模式
        if($file=='debug' && true) return false;
        $filename = dirname(__FILE__).'/logs/'.$file.'_log.php';
        if(!is_file($filename)){
            file_put_contents($filename, '<?php $log = <<<LOG');
        }
        $log = fopen($filename, 'a');
        fwrite($log, date('[Y-m-d H:i:s]').$msg.PHP_EOL);
        fclose($log);
        return true;
    }
}
