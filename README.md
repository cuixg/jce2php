# jce2php
jce2php的工具

使用方式:
php jce2php.php xx.jce "App.ServerName.ServantName"

会生成一个如下的文件夹结构:
- App
    - ServerName
        - ServantName
            - classes: 存放根据struct生成的类
            - jce: 存放jce
            servant.php: 同步方式调用,对应TafAssistantV2.php
            servantAs.php: tsf1.0方式调用,对应TafAssistantAsV2.php
            servantNy.php: tsf2.0方式调用,对应TafAssistantNyV2.php