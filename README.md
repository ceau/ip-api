# ip-api
利用公开接口和本地ip数据库获取ip信息

使用说明
若需要使用百度的api，请用户自行申请 apikey，将ak常量设置好即可
<?php
require_once 'ip2location.php';

echo Ip2location::find('118.28.8.8','ip138'); //从哪获取信息
