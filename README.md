# CommentMailPlus

## typecho下使用mailgun发送评论邮件提醒

### 安装方法

在`typecho/usr/plugins/`下新建一个`CommentMailPlus`文件夹，将git中的所有文件放入，在后台安装插件即可。

### 适用环境

此方法发送邮件利用curl库连接MailGun的HTTPS服务，具有到达率高，延迟低等特点，适合于无法使用SMTP的使用者使用。

如果你想使用MailGun的服务而没有curl库，可以尝试安装CommentToMail插件，然后参考[MailGun User Manual](http://documentation.mailgun.com/user_manual.html#smtp-pop3-and-imap)上的相关说明设置SMTP服务。

经测试，SAE可用。

### 其它问题

请参考MailGun的[User Manual](http://documentation.mailgun.com/)。
