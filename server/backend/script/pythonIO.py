# -*- coding: utf-8 -*

###MySQLdb文档请参考http://mysql-python.sourceforge.net/MySQLdb.html
import MySQLdb

db = MySQLdb.connect("rm-bp1l3fz1m9b8uk4m65o.mysql.rds.aliyuncs.com", "root", "Czkj779656332", "orait");
cursor = db.cursor();

#execute query


##################1#####################
#first get weather data
cursor.execute("select * from weather_data") #请加上经纬度、时间点等查询条件
# weather_data表存储天气文件，表共有5列，分别如下：
# latitude: 经度
# longtitude: 纬度
# time: 时间点
# data: json格式 {k1:v1, k2:v2...} 存储该地区在该时间点的天气信息，可能包含湿度、温度等等天气信息，请按需取出即可
# createTime: 数据入库时间
# updateTime: 数据最后更新时间
row = cursor.fetchone()
while row is not None: #get every row in db
	print row #action
	row = cursor.fetchone()


##################2#####################
#set predict result to mysql db
#数据表为 短期预测结果表 short_load_predict，其他预测类型表结果相同，只是data字段内容不同而已
# CREATE TABLE `short_load_predict` (
#   `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
#   `username` varchar(255) NOT NULL COMMENT '用户名',
#   `date` date NOT NULL COMMENT '数据日期',
#   `regionId` int(11) NOT NULL COMMENT '单位id',
#   `type` tinyint(4) NOT NULL COMMENT '数据类型,1-实际负荷,2-原始预测,3-人工预测,4-橙智预测',
#   `data` json DEFAULT NULL COMMENT '数据,k-v',
#   `emptyCount` int(11) unsigned DEFAULT NULL COMMENT '空数据个数',
#   `zeroCount` int(11) unsigned DEFAULT NULL COMMENT '零数据个数',
#   `skipCount` int(11) unsigned DEFAULT NULL COMMENT '异常阶跃点个数',
#   `invariantCount` int(11) unsigned DEFAULT NULL COMMENT '连续恒定点个数',
#   `createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
#   `modifyTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
#   PRIMARY KEY (`id`),
#   UNIQUE KEY `唯一索引` (`username`,`date`,`regionId`,`type`) USING BTREE,
#   KEY `username` (`username`),
#   KEY `regionId` (`regionId`),
#   KEY `type` (`type`),
#   KEY `date` (`date`)
# ) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8 COMMENT='短期负荷预测数据表'

##插入预测结果数据时，新增type=4的新纪录


##################3#####################
#更新原始数据的统计数据，空数据个数, 零数据个数, 异常阶跃点个数, 连续恒定点个数
#用户导入原始数据存储表为short_load_predict(如上)


##更新时，修复数据请在原有data字段内进行追加，data命名负责为0000,0015代表相应时间片，修复数据命名规则为0000_repair,0015_repair
##即，在原字段名称后拼接_repair。空数据个数, 零数据个数, 异常阶跃点个数, 连续恒定点个数分别放入emptyCount，zeroCount，skipCount，invariantCount字段。