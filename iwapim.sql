-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: iwapim
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.24.04.1

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
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int unsigned DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT '',
  `path` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `mimetype` varchar(190) DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  `dataModificationDate` int unsigned DEFAULT NULL,
  `userOwner` int unsigned DEFAULT NULL,
  `userModification` int unsigned DEFAULT NULL,
  `customSettings` json DEFAULT NULL,
  `hasMetaData` tinyint(1) NOT NULL DEFAULT '0',
  `versionCount` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `fullpath` (`path`,`filename`),
  KEY `parentId` (`parentId`),
  KEY `filename` (`filename`),
  KEY `modificationDate` (`modificationDate`),
  KEY `versionCount` (`versionCount`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assets`
--

LOCK TABLES `assets` WRITE;
/*!40000 ALTER TABLE `assets` DISABLE KEYS */;
INSERT INTO `assets` VALUES (1,0,'folder','','/',NULL,1749928128,1749928128,NULL,1,1,NULL,0,0);
/*!40000 ALTER TABLE `assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assets_image_thumbnail_cache`
--

DROP TABLE IF EXISTS `assets_image_thumbnail_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_image_thumbnail_cache` (
  `cid` int unsigned NOT NULL,
  `name` varchar(190) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `filename` varchar(190) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `modificationDate` int unsigned DEFAULT '0',
  `filesize` int unsigned DEFAULT NULL,
  `width` smallint unsigned DEFAULT NULL,
  `height` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`cid`,`name`,`filename`),
  CONSTRAINT `FK_assets_image_thumbnail_cache_assets` FOREIGN KEY (`cid`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assets_image_thumbnail_cache`
--

LOCK TABLES `assets_image_thumbnail_cache` WRITE;
/*!40000 ALTER TABLE `assets_image_thumbnail_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `assets_image_thumbnail_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assets_metadata`
--

DROP TABLE IF EXISTS `assets_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets_metadata` (
  `cid` int unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `language` varchar(10) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  `type` enum('input','textarea','asset','document','object','date','select','checkbox') DEFAULT NULL,
  `data` longtext,
  PRIMARY KEY (`cid`,`name`,`language`),
  KEY `name` (`name`),
  CONSTRAINT `FK_assets_metadata_assets` FOREIGN KEY (`cid`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assets_metadata`
--

LOCK TABLES `assets_metadata` WRITE;
/*!40000 ALTER TABLE `assets_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `assets_metadata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_items`
--

DROP TABLE IF EXISTS `cache_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_items` (
  `item_id` varbinary(255) NOT NULL,
  `item_data` mediumblob NOT NULL,
  `item_lifetime` int unsigned DEFAULT NULL,
  `item_time` int unsigned NOT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_items`
--

LOCK TABLES `cache_items` WRITE;
/*!40000 ALTER TABLE `cache_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` varchar(50) NOT NULL,
  `name` varchar(190) NOT NULL DEFAULT '',
  `definitionModificationDate` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_collectionrelations`
--

DROP TABLE IF EXISTS `classificationstore_collectionrelations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_collectionrelations` (
  `colId` int unsigned NOT NULL,
  `groupId` int unsigned NOT NULL,
  `sorter` int DEFAULT '0',
  PRIMARY KEY (`colId`,`groupId`),
  KEY `FK_classificationstore_collectionrelations_groups` (`groupId`),
  CONSTRAINT `FK_classificationstore_collectionrelations_groups` FOREIGN KEY (`groupId`) REFERENCES `classificationstore_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_collectionrelations`
--

LOCK TABLES `classificationstore_collectionrelations` WRITE;
/*!40000 ALTER TABLE `classificationstore_collectionrelations` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_collectionrelations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_collections`
--

DROP TABLE IF EXISTS `classificationstore_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_collections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `storeId` int DEFAULT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `storeId` (`storeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_collections`
--

LOCK TABLES `classificationstore_collections` WRITE;
/*!40000 ALTER TABLE `classificationstore_collections` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_groups`
--

DROP TABLE IF EXISTS `classificationstore_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `storeId` int DEFAULT NULL,
  `parentId` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(190) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `storeId` (`storeId`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_groups`
--

LOCK TABLES `classificationstore_groups` WRITE;
/*!40000 ALTER TABLE `classificationstore_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_keys`
--

DROP TABLE IF EXISTS `classificationstore_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_keys` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `storeId` int DEFAULT NULL,
  `name` varchar(190) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` text,
  `type` varchar(190) DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  `definition` json DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `enabled` (`enabled`),
  KEY `type` (`type`),
  KEY `storeId` (`storeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_keys`
--

LOCK TABLES `classificationstore_keys` WRITE;
/*!40000 ALTER TABLE `classificationstore_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_relations`
--

DROP TABLE IF EXISTS `classificationstore_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_relations` (
  `groupId` int unsigned NOT NULL,
  `keyId` int unsigned NOT NULL,
  `sorter` int DEFAULT NULL,
  `mandatory` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`groupId`,`keyId`),
  KEY `FK_classificationstore_relations_classificationstore_keys` (`keyId`),
  KEY `mandatory` (`mandatory`),
  CONSTRAINT `FK_classificationstore_relations_classificationstore_groups` FOREIGN KEY (`groupId`) REFERENCES `classificationstore_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_classificationstore_relations_classificationstore_keys` FOREIGN KEY (`keyId`) REFERENCES `classificationstore_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_relations`
--

LOCK TABLES `classificationstore_relations` WRITE;
/*!40000 ALTER TABLE `classificationstore_relations` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_relations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificationstore_stores`
--

DROP TABLE IF EXISTS `classificationstore_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificationstore_stores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(190) DEFAULT NULL,
  `description` longtext,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificationstore_stores`
--

LOCK TABLES `classificationstore_stores` WRITE;
/*!40000 ALTER TABLE `classificationstore_stores` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificationstore_stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dependencies`
--

DROP TABLE IF EXISTS `dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dependencies` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sourcetype` enum('document','asset','object') NOT NULL DEFAULT 'document',
  `sourceid` int unsigned NOT NULL DEFAULT '0',
  `targettype` enum('document','asset','object') NOT NULL DEFAULT 'document',
  `targetid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `combi` (`sourcetype`,`sourceid`,`targettype`,`targetid`),
  KEY `targettype_targetid` (`targettype`,`targetid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dependencies`
--

LOCK TABLES `dependencies` WRITE;
/*!40000 ALTER TABLE `dependencies` DISABLE KEYS */;
/*!40000 ALTER TABLE `dependencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int unsigned DEFAULT NULL,
  `type` enum('page','link','snippet','folder','hardlink','email') DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT '',
  `path` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `index` int unsigned DEFAULT '0',
  `published` tinyint unsigned DEFAULT '1',
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  `userOwner` int unsigned DEFAULT NULL,
  `userModification` int unsigned DEFAULT NULL,
  `versionCount` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `fullpath` (`path`,`key`),
  KEY `parentId` (`parentId`),
  KEY `key` (`key`),
  KEY `published` (`published`),
  KEY `modificationDate` (`modificationDate`),
  KEY `versionCount` (`versionCount`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,0,'page','','/',999999,1,1749928128,1749928128,1,1,0);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_editables`
--

DROP TABLE IF EXISTS `documents_editables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_editables` (
  `documentId` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(750) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
  `type` varchar(50) DEFAULT NULL,
  `data` longtext,
  PRIMARY KEY (`documentId`,`name`),
  CONSTRAINT `fk_documents_editables_documents` FOREIGN KEY (`documentId`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_editables`
--

LOCK TABLES `documents_editables` WRITE;
/*!40000 ALTER TABLE `documents_editables` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_editables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_email`
--

DROP TABLE IF EXISTS `documents_email`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_email` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `controller` varchar(500) DEFAULT NULL,
  `template` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `from` varchar(255) DEFAULT NULL,
  `replyTo` varchar(255) DEFAULT NULL,
  `cc` varchar(255) DEFAULT NULL,
  `bcc` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `missingRequiredEditable` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_documents_email_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_email`
--

LOCK TABLES `documents_email` WRITE;
/*!40000 ALTER TABLE `documents_email` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_email` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_hardlink`
--

DROP TABLE IF EXISTS `documents_hardlink`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_hardlink` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `sourceId` int DEFAULT NULL,
  `propertiesFromSource` tinyint(1) DEFAULT NULL,
  `childrenFromSource` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sourceId` (`sourceId`),
  CONSTRAINT `fk_documents_hardlink_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_hardlink`
--

LOCK TABLES `documents_hardlink` WRITE;
/*!40000 ALTER TABLE `documents_hardlink` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_hardlink` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_link`
--

DROP TABLE IF EXISTS `documents_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_link` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `internalType` enum('document','asset','object') DEFAULT NULL,
  `internal` int unsigned DEFAULT NULL,
  `direct` varchar(1000) DEFAULT NULL,
  `linktype` enum('direct','internal') DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_documents_link_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_link`
--

LOCK TABLES `documents_link` WRITE;
/*!40000 ALTER TABLE `documents_link` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_page`
--

DROP TABLE IF EXISTS `documents_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_page` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `controller` varchar(500) DEFAULT NULL,
  `template` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(383) DEFAULT NULL,
  `prettyUrl` varchar(255) DEFAULT NULL,
  `contentMainDocumentId` int DEFAULT NULL,
  `targetGroupIds` varchar(255) NOT NULL DEFAULT '',
  `missingRequiredEditable` tinyint unsigned DEFAULT NULL,
  `staticGeneratorEnabled` tinyint unsigned DEFAULT NULL,
  `staticGeneratorLifetime` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prettyUrl` (`prettyUrl`),
  CONSTRAINT `fk_documents_page_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_page`
--

LOCK TABLES `documents_page` WRITE;
/*!40000 ALTER TABLE `documents_page` DISABLE KEYS */;
INSERT INTO `documents_page` VALUES (1,'App\\Controller\\DefaultController::defaultAction','','','',NULL,NULL,'',NULL,NULL,NULL);
/*!40000 ALTER TABLE `documents_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_snippet`
--

DROP TABLE IF EXISTS `documents_snippet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_snippet` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `controller` varchar(500) DEFAULT NULL,
  `template` varchar(255) DEFAULT NULL,
  `contentMainDocumentId` int DEFAULT NULL,
  `missingRequiredEditable` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_documents_snippet_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_snippet`
--

LOCK TABLES `documents_snippet` WRITE;
/*!40000 ALTER TABLE `documents_snippet` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_snippet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents_translations`
--

DROP TABLE IF EXISTS `documents_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_translations` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `sourceId` int unsigned NOT NULL DEFAULT '0',
  `language` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`sourceId`,`language`),
  KEY `id` (`id`),
  KEY `language` (`language`),
  CONSTRAINT `fk_documents_translations_documents` FOREIGN KEY (`id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_translations`
--

LOCK TABLES `documents_translations` WRITE;
/*!40000 ALTER TABLE `documents_translations` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `edit_lock`
--

DROP TABLE IF EXISTS `edit_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `edit_lock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cid` int unsigned NOT NULL DEFAULT '0',
  `ctype` enum('document','asset','object') DEFAULT NULL,
  `userId` int unsigned NOT NULL DEFAULT '0',
  `sessionId` varchar(255) DEFAULT NULL,
  `date` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ctype` (`ctype`),
  KEY `cidtype` (`cid`,`ctype`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `edit_lock`
--

LOCK TABLES `edit_lock` WRITE;
/*!40000 ALTER TABLE `edit_lock` DISABLE KEYS */;
/*!40000 ALTER TABLE `edit_lock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `element_workflow_state`
--

DROP TABLE IF EXISTS `element_workflow_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `element_workflow_state` (
  `cid` int NOT NULL DEFAULT '0',
  `ctype` enum('document','asset','object') NOT NULL,
  `place` text,
  `workflow` varchar(100) NOT NULL,
  PRIMARY KEY (`cid`,`ctype`,`workflow`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `element_workflow_state`
--

LOCK TABLES `element_workflow_state` WRITE;
/*!40000 ALTER TABLE `element_workflow_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `element_workflow_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_blocklist`
--

DROP TABLE IF EXISTS `email_blocklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_blocklist` (
  `address` varchar(190) NOT NULL DEFAULT '',
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_blocklist`
--

LOCK TABLES `email_blocklist` WRITE;
/*!40000 ALTER TABLE `email_blocklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_blocklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_log`
--

DROP TABLE IF EXISTS `email_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `documentId` int unsigned DEFAULT NULL,
  `requestUri` varchar(500) DEFAULT NULL,
  `params` text,
  `from` varchar(500) DEFAULT NULL,
  `replyTo` varchar(255) DEFAULT NULL,
  `to` longtext,
  `cc` longtext,
  `bcc` longtext,
  `sentDate` int unsigned DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `error` text,
  PRIMARY KEY (`id`),
  KEY `sentDate` (`sentDate`,`id`),
  KEY `document_id` (`documentId`),
  FULLTEXT KEY `fulltext` (`from`,`to`,`cc`,`bcc`,`subject`,`params`),
  CONSTRAINT `fk_email_log_documents` FOREIGN KEY (`documentId`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_log`
--

LOCK TABLES `email_log` WRITE;
/*!40000 ALTER TABLE `email_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gridconfig_favourites`
--

DROP TABLE IF EXISTS `gridconfig_favourites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gridconfig_favourites` (
  `ownerId` int NOT NULL,
  `classId` varchar(50) NOT NULL,
  `objectId` int NOT NULL DEFAULT '0',
  `gridConfigId` int NOT NULL,
  `searchType` varchar(50) NOT NULL DEFAULT '',
  `type` enum('asset','object') NOT NULL DEFAULT 'object',
  PRIMARY KEY (`ownerId`,`classId`,`searchType`,`objectId`),
  KEY `classId` (`classId`),
  KEY `searchType` (`searchType`),
  KEY `grid_config_id` (`gridConfigId`),
  CONSTRAINT `fk_gridconfig_favourites_gridconfigs` FOREIGN KEY (`gridConfigId`) REFERENCES `gridconfigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gridconfig_favourites`
--

LOCK TABLES `gridconfig_favourites` WRITE;
/*!40000 ALTER TABLE `gridconfig_favourites` DISABLE KEYS */;
/*!40000 ALTER TABLE `gridconfig_favourites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gridconfig_shares`
--

DROP TABLE IF EXISTS `gridconfig_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gridconfig_shares` (
  `gridConfigId` int NOT NULL,
  `sharedWithUserId` int NOT NULL,
  PRIMARY KEY (`gridConfigId`,`sharedWithUserId`),
  KEY `sharedWithUserId` (`sharedWithUserId`),
  KEY `grid_config_id` (`gridConfigId`),
  CONSTRAINT `fk_gridconfig_shares_gridconfigs` FOREIGN KEY (`gridConfigId`) REFERENCES `gridconfigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gridconfig_shares`
--

LOCK TABLES `gridconfig_shares` WRITE;
/*!40000 ALTER TABLE `gridconfig_shares` DISABLE KEYS */;
/*!40000 ALTER TABLE `gridconfig_shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gridconfigs`
--

DROP TABLE IF EXISTS `gridconfigs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gridconfigs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ownerId` int DEFAULT NULL,
  `classId` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `searchType` varchar(50) DEFAULT NULL,
  `type` enum('asset','object') NOT NULL DEFAULT 'object',
  `config` json DEFAULT NULL,
  `description` longtext,
  `creationDate` int DEFAULT NULL,
  `modificationDate` int DEFAULT NULL,
  `shareGlobally` tinyint(1) DEFAULT NULL,
  `setAsFavourite` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ownerId` (`ownerId`),
  KEY `classId` (`classId`),
  KEY `searchType` (`searchType`),
  KEY `shareGlobally` (`shareGlobally`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gridconfigs`
--

LOCK TABLES `gridconfigs` WRITE;
/*!40000 ALTER TABLE `gridconfigs` DISABLE KEYS */;
/*!40000 ALTER TABLE `gridconfigs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importconfig_shares`
--

DROP TABLE IF EXISTS `importconfig_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `importconfig_shares` (
  `importConfigId` int NOT NULL,
  `sharedWithUserId` int NOT NULL,
  PRIMARY KEY (`importConfigId`,`sharedWithUserId`),
  KEY `sharedWithUserId` (`sharedWithUserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importconfig_shares`
--

LOCK TABLES `importconfig_shares` WRITE;
/*!40000 ALTER TABLE `importconfig_shares` DISABLE KEYS */;
/*!40000 ALTER TABLE `importconfig_shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importconfigs`
--

DROP TABLE IF EXISTS `importconfigs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `importconfigs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ownerId` int DEFAULT NULL,
  `classId` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `config` json DEFAULT NULL,
  `description` longtext,
  `creationDate` int DEFAULT NULL,
  `modificationDate` int DEFAULT NULL,
  `shareGlobally` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ownerId` (`ownerId`),
  KEY `classId` (`classId`),
  KEY `shareGlobally` (`shareGlobally`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importconfigs`
--

LOCK TABLES `importconfigs` WRITE;
/*!40000 ALTER TABLE `importconfigs` DISABLE KEYS */;
/*!40000 ALTER TABLE `importconfigs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lock_keys`
--

DROP TABLE IF EXISTS `lock_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lock_keys` (
  `key_id` varchar(64) NOT NULL,
  `key_token` varchar(44) NOT NULL,
  `key_expiration` int unsigned NOT NULL,
  PRIMARY KEY (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lock_keys`
--

LOCK TABLES `lock_keys` WRITE;
/*!40000 ALTER TABLE `lock_keys` DISABLE KEYS */;
/*!40000 ALTER TABLE `lock_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messenger_messages`
--

LOCK TABLES `messenger_messages` WRITE;
/*!40000 ALTER TABLE `messenger_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messenger_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migration_versions`
--

DROP TABLE IF EXISTS `migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migration_versions` (
  `version` varchar(255) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migration_versions`
--

LOCK TABLES `migration_versions` WRITE;
/*!40000 ALTER TABLE `migration_versions` DISABLE KEYS */;
INSERT INTO `migration_versions` VALUES ('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201007000000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201008082752',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201008091131',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201008101817',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201008132324',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201009095924',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201012154224',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201014101437',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201113143914',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20201201084201',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210218142556',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210323082921',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210323110055',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210324152821',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210324152822',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210330132354',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210408153226',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210412112812',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210428145320',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210505093841',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210531125102',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210608094532',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210616114744',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210624085031',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210630062445',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210702102100',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210901130000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20210928135248',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211016084043',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211018104331',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211028134037',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211028155535',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211103055110',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211209131141',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20211221152344',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220119082511',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220120121803',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220120162621',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220201132131',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220214110000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220317125711',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220318101020',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220402104849',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220411172543',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220419120333',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220425082914',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220502104200',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220506103100',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220511085800',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220614115124',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220617145524',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220718162200',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220725154615',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220809154926',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220809164000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220816120101',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220829132224',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220830105212',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220906111031',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20220908113752',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221003115124',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221005090000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221019042134',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221020195451',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221025165133',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221028115803',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221107084519',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221116115427',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221129084031',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221215071650',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221216140012',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221220152444',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221222134837',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221222181745',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20221228101109',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230107224432',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230111074323',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230113165612',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230120111111',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230124120641',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230125164101',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230202152342',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230221073317',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230222075502',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230222174636',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230223101848',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230320110429',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230320131322',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230321133700',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230322114936',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230405162853',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230406113010',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230424084415',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230428112302',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230517115427',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230525131748',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230615103905',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230616085142',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20230913173812',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20231127124738',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240131080600',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240222143211',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240229152000',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240606165618',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240708083500',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240813085200',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20240916105500',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20241021111028',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20241025101923',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20241114142759',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20250312132759',NULL,NULL),('Pimcore\\Bundle\\CoreBundle\\Migrations\\Version20250416120333',NULL,NULL);
/*!40000 ALTER TABLE `migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `cid` int DEFAULT NULL,
  `ctype` enum('asset','document','object') DEFAULT NULL,
  `date` int DEFAULT NULL,
  `user` int DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` longtext,
  `locked` tinyint unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `cid_ctype` (`cid`,`ctype`),
  KEY `date` (`date`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes_data`
--

DROP TABLE IF EXISTS `notes_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes_data` (
  `auto_id` int NOT NULL AUTO_INCREMENT,
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('text','date','document','asset','object','bool') DEFAULT NULL,
  `data` text,
  PRIMARY KEY (`auto_id`),
  UNIQUE KEY `UNIQ_E5A8E5E2BF3967505E237E06` (`id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes_data`
--

LOCK TABLES `notes_data` WRITE;
/*!40000 ALTER TABLE `notes_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `notes_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL DEFAULT 'info',
  `title` varchar(250) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `sender` int DEFAULT NULL,
  `recipient` int NOT NULL,
  `read` tinyint(1) NOT NULL DEFAULT '0',
  `creationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modificationDate` timestamp NULL DEFAULT NULL,
  `linkedElementType` enum('document','asset','object') DEFAULT NULL,
  `linkedElement` int DEFAULT NULL,
  `payload` longtext,
  `isStudio` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `recipient` (`recipient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `object_url_slugs`
--

DROP TABLE IF EXISTS `object_url_slugs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `object_url_slugs` (
  `objectId` int unsigned NOT NULL DEFAULT '0',
  `classId` varchar(50) NOT NULL DEFAULT '0',
  `fieldname` varchar(70) NOT NULL DEFAULT '0',
  `ownertype` enum('object','fieldcollection','localizedfield','objectbrick') NOT NULL DEFAULT 'object',
  `ownername` varchar(70) NOT NULL DEFAULT '',
  `position` varchar(70) NOT NULL DEFAULT '0',
  `slug` varchar(765) NOT NULL,
  `siteId` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`slug`,`siteId`),
  KEY `objectId` (`objectId`),
  KEY `classId` (`classId`),
  KEY `fieldname` (`fieldname`),
  KEY `position` (`position`),
  KEY `ownertype` (`ownertype`),
  KEY `ownername` (`ownername`),
  KEY `slug` (`slug`),
  KEY `siteId` (`siteId`),
  KEY `fieldname_ownertype_position_objectId` (`fieldname`,`ownertype`,`position`,`objectId`),
  CONSTRAINT `fk_object_url_slugs__objectId` FOREIGN KEY (`objectId`) REFERENCES `objects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `object_url_slugs`
--

LOCK TABLES `object_url_slugs` WRITE;
/*!40000 ALTER TABLE `object_url_slugs` DISABLE KEYS */;
/*!40000 ALTER TABLE `object_url_slugs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `objects`
--

DROP TABLE IF EXISTS `objects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `objects` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int unsigned DEFAULT NULL,
  `type` enum('object','folder','variant') DEFAULT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT '',
  `path` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `index` int unsigned DEFAULT '0',
  `published` tinyint unsigned DEFAULT '1',
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  `userOwner` int unsigned DEFAULT NULL,
  `userModification` int unsigned DEFAULT NULL,
  `classId` varchar(50) DEFAULT NULL,
  `className` varchar(255) DEFAULT NULL,
  `childrenSortBy` enum('key','index') DEFAULT NULL,
  `childrenSortOrder` enum('ASC','DESC') DEFAULT NULL,
  `versionCount` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `fullpath` (`path`,`key`),
  KEY `key` (`key`),
  KEY `index` (`index`),
  KEY `published` (`published`),
  KEY `parentId` (`parentId`),
  KEY `type_path_classId` (`type`,`path`,`classId`),
  KEY `modificationDate` (`modificationDate`),
  KEY `classId` (`classId`),
  KEY `versionCount` (`versionCount`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `objects`
--

LOCK TABLES `objects` WRITE;
/*!40000 ALTER TABLE `objects` DISABLE KEYS */;
INSERT INTO `objects` VALUES (1,0,'folder','','/',999999,1,1749928128,1749928128,1,1,NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `objects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties` (
  `cid` int unsigned NOT NULL DEFAULT '0',
  `ctype` enum('document','asset','object') NOT NULL DEFAULT 'document',
  `cpath` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `name` varchar(190) NOT NULL DEFAULT '',
  `type` enum('text','document','asset','object','bool','select') DEFAULT NULL,
  `data` text,
  `inheritable` tinyint unsigned DEFAULT '1',
  PRIMARY KEY (`cid`,`ctype`,`name`),
  KEY `getall` (`cpath`,`ctype`,`inheritable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quantityvalue_units`
--

DROP TABLE IF EXISTS `quantityvalue_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quantityvalue_units` (
  `id` varchar(50) NOT NULL,
  `group` varchar(50) DEFAULT NULL,
  `abbreviation` varchar(20) DEFAULT NULL,
  `longname` varchar(250) DEFAULT NULL,
  `baseunit` varchar(50) DEFAULT NULL,
  `factor` double DEFAULT NULL,
  `conversionOffset` double DEFAULT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `converter` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_baseunit` (`baseunit`),
  CONSTRAINT `fk_baseunit` FOREIGN KEY (`baseunit`) REFERENCES `quantityvalue_units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quantityvalue_units`
--

LOCK TABLES `quantityvalue_units` WRITE;
/*!40000 ALTER TABLE `quantityvalue_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `quantityvalue_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recyclebin`
--

DROP TABLE IF EXISTS `recyclebin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recyclebin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(20) DEFAULT NULL,
  `subtype` varchar(20) DEFAULT NULL,
  `path` varchar(765) DEFAULT NULL,
  `amount` int DEFAULT NULL,
  `date` int unsigned DEFAULT NULL,
  `deletedby` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recyclebin_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recyclebin`
--

LOCK TABLES `recyclebin` WRITE;
/*!40000 ALTER TABLE `recyclebin` DISABLE KEYS */;
/*!40000 ALTER TABLE `recyclebin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_tasks`
--

DROP TABLE IF EXISTS `schedule_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_tasks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cid` int unsigned DEFAULT NULL,
  `ctype` enum('document','asset','object') DEFAULT NULL,
  `date` int unsigned DEFAULT NULL,
  `action` enum('publish','unpublish','delete','publish-version') DEFAULT NULL,
  `version` bigint unsigned DEFAULT NULL,
  `active` tinyint unsigned DEFAULT '0',
  `userId` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `ctype` (`ctype`),
  KEY `active` (`active`),
  KEY `version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_tasks`
--

LOCK TABLES `schedule_tasks` WRITE;
/*!40000 ALTER TABLE `schedule_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedule_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_backend_data`
--

DROP TABLE IF EXISTS `search_backend_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_backend_data` (
  `id` int NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT '',
  `index` int unsigned DEFAULT '0',
  `fullpath` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `maintype` varchar(8) NOT NULL DEFAULT '',
  `type` varchar(20) DEFAULT NULL,
  `subtype` varchar(190) DEFAULT NULL,
  `published` tinyint unsigned DEFAULT NULL,
  `creationDate` int unsigned DEFAULT NULL,
  `modificationDate` int unsigned DEFAULT NULL,
  `userOwner` int DEFAULT NULL,
  `userModification` int DEFAULT NULL,
  `data` longtext,
  `properties` text,
  PRIMARY KEY (`id`,`maintype`),
  KEY `key` (`key`),
  KEY `index` (`index`),
  KEY `fullpath` (`fullpath`),
  KEY `maintype` (`maintype`),
  KEY `type` (`type`),
  KEY `subtype` (`subtype`),
  KEY `published` (`published`),
  FULLTEXT KEY `fulltext` (`data`,`properties`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_backend_data`
--

LOCK TABLES `search_backend_data` WRITE;
/*!40000 ALTER TABLE `search_backend_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_backend_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_store`
--

DROP TABLE IF EXISTS `settings_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_store` (
  `id` varchar(190) NOT NULL DEFAULT '',
  `scope` varchar(190) NOT NULL DEFAULT '',
  `data` longtext,
  `type` enum('bool','int','float','string') NOT NULL DEFAULT 'string',
  PRIMARY KEY (`id`,`scope`),
  KEY `scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_store`
--

LOCK TABLES `settings_store` WRITE;
/*!40000 ALTER TABLE `settings_store` DISABLE KEYS */;
INSERT INTO `settings_store` VALUES ('BUNDLE_INSTALLED__Pimcore\\Bundle\\AdminBundle\\PimcoreAdminBundle','pimcore','1','bool'),('BUNDLE_INSTALLED__Pimcore\\Bundle\\SimpleBackendSearchBundle\\PimcoreSimpleBackendSearchBundle','pimcore','1','bool');
/*!40000 ALTER TABLE `settings_store` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mainDomain` varchar(255) DEFAULT NULL,
  `domains` text,
  `rootId` int unsigned DEFAULT NULL,
  `errorDocument` varchar(255) DEFAULT NULL,
  `localizedErrorDocuments` text,
  `redirectToMainDomain` tinyint(1) DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rootId` (`rootId`),
  CONSTRAINT `fk_sites_documents` FOREIGN KEY (`rootId`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int unsigned DEFAULT NULL,
  `idPath` varchar(190) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idPath_name` (`idPath`,`name`),
  KEY `idpath` (`idPath`),
  KEY `parentid` (`parentId`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags_assignment`
--

DROP TABLE IF EXISTS `tags_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags_assignment` (
  `tagid` int unsigned NOT NULL DEFAULT '0',
  `cid` int NOT NULL DEFAULT '0',
  `ctype` enum('document','asset','object') NOT NULL,
  PRIMARY KEY (`tagid`,`cid`,`ctype`),
  KEY `ctype` (`ctype`),
  KEY `ctype_cid` (`cid`,`ctype`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags_assignment`
--

LOCK TABLES `tags_assignment` WRITE;
/*!40000 ALTER TABLE `tags_assignment` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags_assignment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tmp_store`
--

DROP TABLE IF EXISTS `tmp_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tmp_store` (
  `id` varchar(190) NOT NULL DEFAULT '',
  `tag` varchar(190) DEFAULT NULL,
  `data` longtext,
  `serialized` tinyint NOT NULL DEFAULT '0',
  `date` int unsigned DEFAULT NULL,
  `expiryDate` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`),
  KEY `date` (`date`),
  KEY `expiryDate` (`expiryDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tmp_store`
--

LOCK TABLES `tmp_store` WRITE;
/*!40000 ALTER TABLE `tmp_store` DISABLE KEYS */;
/*!40000 ALTER TABLE `tmp_store` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `translations_admin`
--

DROP TABLE IF EXISTS `translations_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translations_admin` (
  `key` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `type` varchar(10) DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT '',
  `text` text,
  `creationDate` int unsigned DEFAULT NULL,
  `modificationDate` int unsigned DEFAULT NULL,
  `userOwner` int unsigned DEFAULT NULL,
  `userModification` int unsigned DEFAULT NULL,
  PRIMARY KEY (`key`,`language`),
  KEY `language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `translations_admin`
--

LOCK TABLES `translations_admin` WRITE;
/*!40000 ALTER TABLE `translations_admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `translations_admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `translations_messages`
--

DROP TABLE IF EXISTS `translations_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translations_messages` (
  `key` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `type` varchar(10) DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT '',
  `text` text,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  `userOwner` int unsigned DEFAULT NULL,
  `userModification` int unsigned DEFAULT NULL,
  PRIMARY KEY (`key`,`language`),
  KEY `language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `translations_messages`
--

LOCK TABLES `translations_messages` WRITE;
/*!40000 ALTER TABLE `translations_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `translations_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tree_locks`
--

DROP TABLE IF EXISTS `tree_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tree_locks` (
  `id` int NOT NULL DEFAULT '0',
  `type` enum('asset','document','object') NOT NULL DEFAULT 'asset',
  `locked` enum('self','propagate') DEFAULT NULL,
  PRIMARY KEY (`id`,`type`),
  KEY `type` (`type`),
  KEY `locked` (`locked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tree_locks`
--

LOCK TABLES `tree_locks` WRITE;
/*!40000 ALTER TABLE `tree_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tree_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parentId` int unsigned DEFAULT NULL,
  `type` enum('user','userfolder','role','rolefolder') NOT NULL DEFAULT 'user',
  `name` varchar(50) DEFAULT NULL,
  `password` varchar(190) DEFAULT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'en',
  `datetimeLocale` varchar(10) DEFAULT '',
  `contentLanguages` longtext,
  `admin` tinyint unsigned DEFAULT '0',
  `active` tinyint unsigned DEFAULT '1',
  `permissions` text,
  `roles` varchar(1000) DEFAULT NULL,
  `welcomescreen` tinyint(1) DEFAULT NULL,
  `closeWarning` tinyint(1) DEFAULT NULL,
  `memorizeTabs` tinyint(1) DEFAULT NULL,
  `allowDirtyClose` tinyint unsigned DEFAULT '1',
  `docTypes` text,
  `classes` text,
  `twoFactorAuthentication` varchar(255) DEFAULT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `activePerspective` varchar(255) DEFAULT NULL,
  `perspectives` longtext,
  `websiteTranslationLanguagesEdit` longtext,
  `websiteTranslationLanguagesView` longtext,
  `lastLogin` int unsigned DEFAULT NULL,
  `keyBindings` json DEFAULT NULL,
  `passwordRecoveryToken` varchar(290) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type`,`name`),
  KEY `parentId` (`parentId`),
  KEY `name` (`name`),
  KEY `password` (`password`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (0,0,'user','system',NULL,NULL,NULL,NULL,'en','',NULL,1,1,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,0,'user','admin','$2y$10$nMnIdHbBKzdKq53pQuWhKuGsjhYRGoaycEjHjUNXKqRp0GCW2qHIK',NULL,NULL,NULL,'en','',NULL,1,1,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_permission_definitions`
--

DROP TABLE IF EXISTS `users_permission_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_permission_definitions` (
  `key` varchar(50) NOT NULL DEFAULT '',
  `category` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_permission_definitions`
--

LOCK TABLES `users_permission_definitions` WRITE;
/*!40000 ALTER TABLE `users_permission_definitions` DISABLE KEYS */;
INSERT INTO `users_permission_definitions` VALUES ('admin_translations','Pimcore Admin Bundle'),('asset_metadata',''),('assets',''),('classes',''),('classificationstore',''),('clear_cache',''),('clear_fullpage_cache',''),('clear_temp_files',''),('dashboards',''),('document_types',''),('documents',''),('emails',''),('fieldcollections',''),('gdpr_data_extractor','Pimcore Admin Bundle'),('notes_events',''),('notifications',''),('notifications_send',''),('objectbricks',''),('objects',''),('objects_sort_method',''),('predefined_properties',''),('quantityValueUnits',''),('recyclebin',''),('redirects',''),('seemode',''),('selectoptions',''),('share_configurations',''),('sites',''),('system_appearance_settings','Pimcore Admin Bundle'),('system_settings',''),('tags_assignment',''),('tags_configuration',''),('tags_search',''),('thumbnails',''),('translations',''),('users',''),('website_settings',''),('workflow_details','');
/*!40000 ALTER TABLE `users_permission_definitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_workspaces_asset`
--

DROP TABLE IF EXISTS `users_workspaces_asset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_workspaces_asset` (
  `cid` int unsigned NOT NULL DEFAULT '0',
  `cpath` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `userId` int unsigned NOT NULL DEFAULT '0',
  `list` tinyint(1) DEFAULT '0',
  `view` tinyint(1) DEFAULT '0',
  `publish` tinyint(1) DEFAULT '0',
  `delete` tinyint(1) DEFAULT '0',
  `rename` tinyint(1) DEFAULT '0',
  `create` tinyint(1) DEFAULT '0',
  `settings` tinyint(1) DEFAULT '0',
  `versions` tinyint(1) DEFAULT '0',
  `properties` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`cid`,`userId`),
  UNIQUE KEY `cpath_userId` (`cpath`,`userId`),
  UNIQUE KEY `idx_users_workspaces_list_permission` (`userId`,`cpath`,`list`),
  KEY `userId` (`userId`),
  CONSTRAINT `fk_users_workspaces_asset_assets` FOREIGN KEY (`cid`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_workspaces_asset_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_workspaces_asset`
--

LOCK TABLES `users_workspaces_asset` WRITE;
/*!40000 ALTER TABLE `users_workspaces_asset` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_workspaces_asset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_workspaces_document`
--

DROP TABLE IF EXISTS `users_workspaces_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_workspaces_document` (
  `cid` int unsigned NOT NULL DEFAULT '0',
  `cpath` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `userId` int unsigned NOT NULL DEFAULT '0',
  `list` tinyint unsigned DEFAULT '0',
  `view` tinyint unsigned DEFAULT '0',
  `save` tinyint unsigned DEFAULT '0',
  `publish` tinyint unsigned DEFAULT '0',
  `unpublish` tinyint unsigned DEFAULT '0',
  `delete` tinyint unsigned DEFAULT '0',
  `rename` tinyint unsigned DEFAULT '0',
  `create` tinyint unsigned DEFAULT '0',
  `settings` tinyint unsigned DEFAULT '0',
  `versions` tinyint unsigned DEFAULT '0',
  `properties` tinyint unsigned DEFAULT '0',
  PRIMARY KEY (`cid`,`userId`),
  UNIQUE KEY `cpath_userId` (`cpath`,`userId`),
  UNIQUE KEY `idx_users_workspaces_list_permission` (`userId`,`cpath`,`list`),
  KEY `userId` (`userId`),
  CONSTRAINT `fk_users_workspaces_document_documents` FOREIGN KEY (`cid`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_workspaces_document_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_workspaces_document`
--

LOCK TABLES `users_workspaces_document` WRITE;
/*!40000 ALTER TABLE `users_workspaces_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_workspaces_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_workspaces_object`
--

DROP TABLE IF EXISTS `users_workspaces_object`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_workspaces_object` (
  `cid` int unsigned NOT NULL DEFAULT '0',
  `cpath` varchar(765) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `userId` int unsigned NOT NULL DEFAULT '0',
  `list` tinyint unsigned DEFAULT '0',
  `view` tinyint unsigned DEFAULT '0',
  `save` tinyint unsigned DEFAULT '0',
  `publish` tinyint unsigned DEFAULT '0',
  `unpublish` tinyint unsigned DEFAULT '0',
  `delete` tinyint unsigned DEFAULT '0',
  `rename` tinyint unsigned DEFAULT '0',
  `create` tinyint unsigned DEFAULT '0',
  `settings` tinyint unsigned DEFAULT '0',
  `versions` tinyint unsigned DEFAULT '0',
  `properties` tinyint unsigned DEFAULT '0',
  `lEdit` text,
  `lView` text,
  `layouts` text,
  PRIMARY KEY (`cid`,`userId`),
  UNIQUE KEY `cpath_userId` (`cpath`,`userId`),
  UNIQUE KEY `idx_users_workspaces_list_permission` (`userId`,`cpath`,`list`),
  KEY `userId` (`userId`),
  CONSTRAINT `fk_users_workspaces_object_objects` FOREIGN KEY (`cid`) REFERENCES `objects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_workspaces_object_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_workspaces_object`
--

LOCK TABLES `users_workspaces_object` WRITE;
/*!40000 ALTER TABLE `users_workspaces_object` DISABLE KEYS */;
/*!40000 ALTER TABLE `users_workspaces_object` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `versions`
--

DROP TABLE IF EXISTS `versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cid` int unsigned DEFAULT NULL,
  `ctype` enum('document','asset','object') DEFAULT NULL,
  `userId` int unsigned DEFAULT NULL,
  `note` text,
  `stackTrace` text,
  `date` int unsigned DEFAULT NULL,
  `public` tinyint unsigned NOT NULL DEFAULT '0',
  `serialized` tinyint unsigned DEFAULT '0',
  `versionCount` int unsigned NOT NULL DEFAULT '0',
  `binaryFileHash` varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `binaryFileId` bigint unsigned DEFAULT NULL,
  `autoSave` tinyint NOT NULL DEFAULT '0',
  `storageType` varchar(5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `ctype_cid` (`ctype`,`cid`),
  KEY `date` (`date`),
  KEY `public` (`public`),
  KEY `binaryFileHash` (`binaryFileHash`),
  KEY `autoSave` (`autoSave`),
  KEY `stackTrace` (`stackTrace`(1)),
  KEY `versionCount` (`versionCount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versions`
--

LOCK TABLES `versions` WRITE;
/*!40000 ALTER TABLE `versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webdav_locks`
--

DROP TABLE IF EXISTS `webdav_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webdav_locks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner` varchar(100) DEFAULT NULL,
  `timeout` int unsigned DEFAULT NULL,
  `created` int DEFAULT NULL,
  `token` varbinary(100) DEFAULT NULL,
  `scope` tinyint DEFAULT NULL,
  `depth` tinyint DEFAULT NULL,
  `uri` varbinary(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `uri` (`uri`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webdav_locks`
--

LOCK TABLES `webdav_locks` WRITE;
/*!40000 ALTER TABLE `webdav_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `webdav_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_settings`
--

DROP TABLE IF EXISTS `website_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `website_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL DEFAULT '',
  `type` enum('text','document','asset','object','bool') DEFAULT NULL,
  `data` text,
  `language` varchar(15) NOT NULL DEFAULT '',
  `siteId` int unsigned DEFAULT NULL,
  `creationDate` int unsigned DEFAULT '0',
  `modificationDate` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `siteId` (`siteId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_settings`
--

LOCK TABLES `website_settings` WRITE;
/*!40000 ALTER TABLE `website_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `website_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-14 20:15:24
