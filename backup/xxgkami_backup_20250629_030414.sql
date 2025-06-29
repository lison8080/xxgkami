-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: xxgkami
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','$2y$10$a3UUXNeu5xcvnrwamVytv.4TafV7d1kgoYYHY5AqBjK10SDUlbYEW','2025-06-29 18:01:24',NULL);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(50) NOT NULL COMMENT 'API密钥名称',
  `api_key` varchar(32) NOT NULL COMMENT 'API密钥',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:0禁用,1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_use_time` datetime DEFAULT NULL COMMENT '最后使用时间',
  `use_count` int NOT NULL DEFAULT '0' COMMENT '使用次数',
  `description` varchar(255) DEFAULT NULL COMMENT '备注说明',
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_keys`
--

LOCK TABLES `api_keys` WRITE;
/*!40000 ALTER TABLE `api_keys` DISABLE KEYS */;
INSERT INTO `api_keys` VALUES (1,'test','37973705320b619f17902e75d7638519',1,'2025-06-27 18:30:05','2025-06-29 18:03:37',17,'');
/*!40000 ALTER TABLE `api_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cards`
--

DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `card_key` varchar(32) NOT NULL COMMENT '原始卡密',
  `encrypted_key` varchar(40) NOT NULL COMMENT '加密后的卡密',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:未使用 1:已使用 2:已停用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `use_time` datetime DEFAULT NULL,
  `expire_time` datetime DEFAULT NULL,
  `duration` int NOT NULL DEFAULT '0',
  `verify_method` enum('web','post','get') DEFAULT NULL,
  `allow_reverify` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否允许同设备重复验证(1:允许, 0:不允许)',
  `device_id` varchar(64) DEFAULT NULL,
  `encryption_type` varchar(10) NOT NULL DEFAULT 'sha1' COMMENT '加密类型 (sha1, rc4)',
  `card_type` enum('time','count') NOT NULL DEFAULT 'time' COMMENT '卡密类型：time-时间卡密，count-次数卡密',
  `total_count` int NOT NULL DEFAULT '0' COMMENT '总次数（次数卡密专用）',
  `remaining_count` int NOT NULL DEFAULT '0' COMMENT '剩余次数（次数卡密专用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `card_key` (`card_key`),
  UNIQUE KEY `encrypted_key` (`encrypted_key`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cards`
--

LOCK TABLES `cards` WRITE;
/*!40000 ALTER TABLE `cards` DISABLE KEYS */;
INSERT INTO `cards` VALUES (1,'3cp0KLVvkZH6KY7yPLbV','114fbf0e7ae32214b18b436773f6d23f57d00538',0,'2025-06-29 18:01:55',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(2,'6h0tebYnMaEsGTNMfNPg','bf0ee57ea5c7fa9ac01bec3a0df6ee125f6400d9',0,'2025-06-29 18:01:55',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(3,'w91DZe8PZ27EFOjr3gue','2755bcdadeb78e8787e4ecc89187f804336d4fd5',0,'2025-06-29 18:01:55',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(4,'tZlWR97wxNACABaqhMJI','5723346b88a536bceefca8aeb7c838172339e62f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(5,'0HwI5hKqnET9uClOc5BA','ef831fd43bf3bff21cb61f53b8ab764a59e809b1',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(6,'bmGXWradTlQJzKrY7yKx','2e44160a255c0be8c92cc1e2c9f111ca66c4d23e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(7,'oSFQBkVV5wqpkeq99wt1','db9e0a8c231a80e3f373fc6c2fc193ce3121410c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(8,'KaMNTVmSlNhZAw5gixjE','498e170d8389d14984920d4e796a9d62e53a7aca',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(9,'3Ffd3ZCvj80mIWbPljPb','9b934390630f0bfdb4a2a01473ca38da6d1218c3',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(10,'ncZjqbHgcvtid7RohYKm','2527eb567aeec63f801cac302569fa2653fa1a6c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(11,'qQNkY3V6M3GFovnyiijS','f0d696b2c423dc12e3ff7f21cfaea6838c69e346',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(12,'15PGTH5qZ4k0nrs0T2W6','56954ac3f77a314e3c6ecb741acbaea3f67e43c9',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(13,'kKRXFS161HUDfiDfc3nF','98960fb9ce3dd0a759755a0f4a1657d8bf69cb8c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(14,'HOFAjjPyww5yiXlrsN02','aa1d86c3ce5f4a142b2d7735147550e1a1f666ef',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(15,'tZNRMnTrINeE60GYxckX','1384f564937f1e4fe69168894512c76838ecd736',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(16,'FErk0CirP8ScLENzsVYp','f84fceb48cac95d1d98b12fbea9fae26af6d0515',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(17,'39jF75injJZtA2xMQ8jj','6e73271b534b6452b2d0d47a678b8070f3830a54',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(18,'wBSM6uykQgf9RXAtxoQy','f140fea398c32209a7557e0880be6cf04a600581',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(19,'OcvFOphA0uUSTiAYy3Te','51026bd69e62012298fbe28a10a39fe320bf7ba5',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(20,'jW1Z8AetJ7OGd6rstouA','e82f5bede2f937fd799ab52f80e7c8d9ef8dc818',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(21,'DLDhOQ3r3rXd2GeA2IbT','a585b64c8ecf1e765788e2d3756a61f0678ebfe1',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(22,'agFfUMNG2zbKGyOpTUdI','0815abef036b08ce34957d7a633a1874157c46c0',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(23,'yHdsidevQyLUoB0u6uko','d8387f1f67889b0a742d962da5977cf655f57d72',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(24,'vV31hKncBEUIhKMLMX2M','ac9c554e8d2a061a110bec646048b8fbe5d5e93c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(25,'b152yCey6yAbIeEVJl2b','19f540d2f1ce78e2a71e416fa2f530376421a72f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(26,'qXQPZnSSKliT03QjspmX','05f730bda7c5dd243c75fac8dc85090af2cb619c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(27,'SAHCN9E3AxNIfnu76GTZ','54b62168d1a7556655135fd20ae5412b9b7d80e7',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(28,'1zHWsCwNThp7biBW3pyd','6605f2356fa8e164c6134b443017e581a0283230',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(29,'luRJkXwtiysaiQ9a2aV3','fe268b40b8426e8cf3b567c81a9e4dcd5408e798',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(30,'pYj3q2TsRDD8aSrhakFj','7b158388d8b8748fbdc61ddc612324b37b3dde67',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(31,'cIbIWgvUoTKo9SfaoxPR','8a4d3c9a572eaa42ce038d1e681fe94f109c141e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(32,'fujvP0lAtiflbQS9E3vw','a580b91e27e4ab4b3c1cd88f9bcfc2b4ece69f6e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(33,'ebntUQpA4zFsLhJKKFZ1','e32cb7b78efc22a6bbe74b06b27300e9bf10ab77',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(34,'yq2s7qJzEWlZVrzwcml0','6300aaf166cc68f0c8e7fa368caef3a322d1b5be',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(35,'ztoUbLQiqjN17foUQ5HZ','b484bfc7e12f3e8b215bdf908d002677e46652a1',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(36,'7Q33QXUGUXGkLrXJu3Lq','68f156a27bda30211eab82042b2c145e73cf143b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(37,'rvLMjb0ofX0Xp9xBJIBx','c3df61e3cb2b28c4aab5f708be337c928eadaeeb',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(38,'ZMunizujkGa8n6mIsQdw','59994d411bb88ad024c8c525f3982c5d9771fb54',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(39,'RPmVD1g3w3s3BaioNvJP','e9cfd799d7d6dc591dc738e1f6dc67f534456d26',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(40,'MFGvDs7ONebjnhgXub1o','87c95611e73c890245cc73af307f9b4c7811a290',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(41,'6OOdMvtrNttPDOERs424','bb36b94211a273663b59a349243e8691bb01d55b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(42,'Ij3O7jUI9q1mITabfbMi','cb143ab5525b47c196bf87f18a510c7be7711696',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(43,'Sd3wqv2Kh69SxcAVRyAB','39a67bd9b7cda50e50be6b2d4d4439157a44420e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(44,'WOip1WYYwFxAJvOEXusn','c9ec489a15f1e6587b3e071f56cde910603c2295',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(45,'n0wd4KpTDIev53GDXeC6','2a404cdebf328b5c64fc852212aa616427040b44',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(46,'IfOJXn3T9QQOU5qMYgk7','85448efe67ed0d79d946cbf678fa5fe846026f69',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(47,'bFQaLgsB7rkTT205QcTU','5a029d697a73258472016a52e62291f606f5576b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(48,'R8bz7xZenG9kzpL4gyQ0','d699d610ce091f9925aec9779b0499fec6e49a8f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(49,'O5N1djiXBvJtSBcf1smD','039a8d922d0e1d97aa124738d3c6934e19534ff2',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(50,'JO9mMDXVImzbzZDrFAuZ','b4718f32d5a734c6b03206b49036fd59740b41e5',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(51,'bfFbfCncBV0jubgHzaa7','60ae5c28bc7607ce825d4eadcc232a40aa14a28b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(52,'gxpSIhX3LeldK08PR2KE','eaf9b3d452c59cdb689ad2a2ef79c9f62e5b4c55',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(53,'kqEL2hKuxok3N9PRlkDf','a1b4e0ca129b08c75744d2213445e177accc35e2',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(54,'WzmjLDmF4I5oUqk6xaKe','88ded141dde38e88989b522791ac9bd5a93a4142',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(55,'6orz8vGxIbNyFP3tv1Sr','8ab1497d61e6dd4fd8670a9db98e6a47283e9ea8',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(56,'6Pi6BsfAhsnvpujEdCrn','595e413893dad204e09bcffcaef4333d47db4cf9',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(57,'26fBO4EsxS0rfOABPoNa','d00af493c609983e0c91a227632604849977233f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(58,'sAjcoY7AnSkcNaHhBmHH','d70741ebaace4cb2f57de93403ba4fc00a21fa1b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(59,'3MiYyZj0TXGNCoaN9nR3','6ded2ff26a5e9dc0c5ccd7cabd1c00de00094356',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(60,'cjXogVyKfycoEStbP1c5','91a818fe1b629a8c31cea74a31f7b5299ae89434',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(61,'lseNGr2calKwpvVmXMXV','e2b9557cde6e49a967e187f486cad2f8cb4bcc41',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(62,'XjsRubFPm00JG1C0ZAbR','0d20a57c488e825f0e67eae6c413279e07a18b9e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(63,'AbIQULqGhg01WP1f8jdL','da2d78cec6ec86a4df3a299800da98e2e1ad40ed',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(64,'dgK2eavPVIofQAVUj9Z7','b46e05abb2405289cfe9eeb92e7b9f84b415c957',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(65,'bAKw5Ae7SSDox9yka3Zb','c4a073d356b832c9fd3fdcc4cbc6a61a26ae7bb5',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(66,'vvG1GVFJtTvKiXAnv44K','cbf49d98effdd98ba83565f39fffaccce1246415',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(67,'OwpplEErcDTZU0iile3y','aa6d88c85ca0e02be90616ce910942ea2677e65a',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(68,'0peOi0VjE2igdYQ0r19l','8d93fca381f2d1c7750cb2db2bcbd8bd1247073b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(69,'01tbX9OzrqWcn1XFDfkt','740d244c35bb00516ad1b3d64194deacfd76f8a4',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(70,'LhQqG5Uxqt2cKMI7lGPc','bb49c06673731d3f861fcf128a917ce02c82f7a4',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(71,'ssvKUwJuF3jNCjxL0u0q','d6476ca0f26abe66db7baea93cd7adc2cb352c6e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(72,'8WzhwkERdBIgOZuT3C2w','3b9d2a445d0552127ca67f475c6736913016464c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(73,'BkT55avCvEyN7esowAhR','4b4b07aa6c71a04c79d6e6e6fe03c46dccde150c',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(74,'kYz6pF5WXuKQSIzfpqbp','9127b1a6a98692db9193f4d4852e4f5a142dff5b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(75,'KRoecJPyLUI0YlVK8NK7','d30a380f6506cc99caa0bc97bd486bbfa363140f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(76,'VYF0tdo9H7a06F4Jbu2f','a3a87ae658b7d0d803cd6e9b7df6677477aa3564',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(77,'F0wjVqoXaXTPrRC5fKGW','b068cbdd875f75a5534ccdbffdee0769fd9e314d',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(78,'7SuOowS7TK4K2Ap0cWBo','ccf70a0e02d08b08e450196f8b42e44bd398a3bb',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(79,'yfz9G1pE2nF3qxyZSrz7','36cf5bd0676e8d1f2b2732c3934dfd6fba52a6ff',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(80,'XtHCJv2iHXugViKp7lpb','da47531cbf1466994e8636ea11166b0d9c4eaee4',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(81,'FxiTZUkFgOpSd9QUS1Am','6dad55c877166256f95602d72b95bd1549fcbbf0',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(82,'Xh9tuwXUPVZxA1NKwyiU','812b066a917e19e66dd7e4d0ad8a1a69e0bf4c5b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(83,'K244zCMCYRGtYzdP69fH','8d0047e66078415785dc60e0d4711f14d6c195c8',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(84,'huHZh0Ls0yF2xYqw9MQ6','ada04cff48f7879e712c3b1751ae9ba9960c8f47',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(85,'Ach9u26r8V0NTESUTevj','8d3ba469cf8b64a09e94f51956ece89104c3ba7a',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(86,'cvZsXohy9QZ5LEQFqiu5','b0d915eaede6a8c00867fa2937f0222e66e5bf06',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(87,'uA6TYiBzENdWvw9Txjab','69214c8d93b47d0d5cbe3454eed26e5ee3814d34',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(88,'q7rFC3XaHDwqY1x8U39O','fca9bfd514efa6bcb12aeb2bce9d935efb5c3e00',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(89,'MMf7BPaYvtae2RGzeWEk','d24bf6347a5c9dfbcfc8f5b5f0239f5a792a4a5e',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(90,'ShRa6FQ99flt5nT3GyQn','e2eb3bc3288cd07be8a2d752f4785b557864c889',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(91,'pTaDaLu3ST7t1x3Hrv0f','27e69b4915f459c3de2e6b0c527de04d6aafd2bd',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(92,'MWK5isZcS8bABitkKotC','15ff57879d6942ec0d5fa80706a2d7edf5d05741',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(93,'bp0IeS8XWfobQyCa3L5g','16fc37dc74edd2275ae4fae9c85b16a48c2a6684',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(94,'wWVibMVKIxnXCUWuAVHr','c718954e6acf790f2984eb2e6ef466dd87151a46',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(95,'PhzeQbdBPThaHKDkwP1M','0f194a1949c5d381a68fb2e4e8704f57812aa5d5',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(96,'4Uy5PXYSswVaSGfW0Gx4','2d9b32709159f60636a378405466a559e95cdd08',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(97,'rIyXBKyHNbSGl8UGFNKW','4ea6d4487da4dc33e2dcc0ddb7af74c0baac128f',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(98,'kfwlewRFO1vdxYyebRfH','db05eb6c864dc7f5a50b6062a31d5bdeea9f7665',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(99,'uWKrI4JWkSozylp20gZC','c3a24e93c185429bf34073796366d6484d814377',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(100,'V4Wc1a1VtvUKJQVvrGpW','05d1407ee0d6ecd2645e9a7a33f5c51e23acf74b',0,'2025-06-29 18:01:56',NULL,NULL,0,NULL,1,NULL,'sha1','time',0,0),(101,'iarCLN5op5y4CYgHYUu1','09d9aa51fd4221d53abbf7eac514b6a50da1853d',1,'2025-06-29 18:01:56','2025-06-29 18:03:37',NULL,0,'post',1,'34dd8a14-40a0-44e5-9eac-b0c68e87e148','sha1','time',0,0);
/*!40000 ALTER TABLE `cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `features`
--

DROP TABLE IF EXISTS `features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `features` (
  `id` int NOT NULL AUTO_INCREMENT,
  `icon` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `features`
--

LOCK TABLES `features` WRITE;
/*!40000 ALTER TABLE `features` DISABLE KEYS */;
INSERT INTO `features` VALUES (1,'fas fa-shield-alt','安全可靠','采用先进的加密技术，确保卡密数据安全\n数据加密存储\n防暴力破解\n安全性验证',1,1),(2,'fas fa-code','API接口','提供完整的API接口，支持多种验证方式\nRESTful API\n多种验证方式\n详细接口文档',2,1),(3,'fas fa-tachometer-alt','高效稳定','系统运行稳定，响应迅速\n快速响应\n稳定运行\n性能优化',3,1),(4,'fas fa-chart-line','数据统计','详细的数据统计和分析功能\n实时统计\n数据分析\n图表展示',4,1);
/*!40000 ALTER TABLE `features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_title','小小怪卡密验证系统'),(2,'site_subtitle','专业的卡密验证解决方案'),(3,'copyright_text','小小怪卡密系统 - All Rights Reserved'),(4,'contact_qq_group','123456789'),(5,'contact_wechat_qr','assets/images/wechat-qr.jpg'),(6,'contact_email','support@example.com'),(7,'api_enabled','1'),(8,'api_key','b91c251b4dc58d9b57d4e7a8461f9306');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slides`
--

DROP TABLE IF EXISTS `slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slides`
--

LOCK TABLES `slides` WRITE;
/*!40000 ALTER TABLE `slides` DISABLE KEYS */;
INSERT INTO `slides` VALUES (1,'安全可靠的验证系统','采用先进的加密技术，确保您的数据安全','assets/images/slide1.jpg',1,1,'2025-06-29 18:01:24'),(2,'便捷高效的验证流程','支持多种验证方式，快速响应','assets/images/slide2.jpg',2,1,'2025-06-29 18:01:24'),(3,'完整的API接口','提供丰富的接口，便于集成','assets/images/slide3.jpg',3,1,'2025-06-29 18:01:24');
/*!40000 ALTER TABLE `slides` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-29 18:04:17
