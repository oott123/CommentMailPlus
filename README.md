# CommentMailPlus

## 评论邮件通知插件

一款 Typecho 评论邮件提醒插件

- 基于 PHP 插件 cURL 实现
- 基于 MailGun API 实现

### 安装方法

进入插件目录

```bash
$ cd typecho/usr/plugins/
```

> 小贴士：此处路径请根据实际网址路径进行调整，此处仅为样例。

克隆插件文件

```bash
$ git clone https://github.com/oott123/CommentMailPlus.git
```

在后台启用即可

### 适用环境

本插件发送邮件利用 cURL 组件连接 MailGun 的 HTTPS 接口，具有到达率高，延迟低等特点，适合于无法使用 SMTP 的环境使用。

若当前 PHP 环境无法修改以支持 cURL 组件，可以尝试使用 [CommentToMail](http://docs.typecho.org/plugins/commenttomail) 插件，然后参照 [MailGun User Manual](https://documentation.mailgun.com/en/latest/user_manual.html#introduction) 上的相关说明设置 SMTP 服务。

经测试，SAE可用。

### 感谢

[@Cain](https://github.com/Vndroid)

### 其它问题

有问题请发 issues ，欢迎提交代码。其他关于 MailGun 说明可在[官方文档](https://documentation.mailgun.com/en/latest/)中查看。