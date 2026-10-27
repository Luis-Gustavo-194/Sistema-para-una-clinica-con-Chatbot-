CREATE DATABASE  IF NOT EXISTS `sistema_citas_medicas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `sistema_citas_medicas`;
-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: sistema_citas_medicas
-- ------------------------------------------------------
-- Server version	9.7.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'ba3f3aff-6dc5-11f1-a14f-ec9a0c111a2e:1-86';

--
-- Table structure for table `auditorias`
--

DROP TABLE IF EXISTS `auditorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int DEFAULT NULL,
  `direccion_ip` varchar(45) DEFAULT NULL,
  `fecha_hora` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `auditorias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditorias`
--

LOCK TABLES `auditorias` WRITE;
/*!40000 ALTER TABLE `auditorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `citas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `medico_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('Pendiente','Confirmada','Atendida','Cancelada','No Asistio') DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `medico_id` (`medico_id`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`medico_id`) REFERENCES `medicos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

LOCK TABLES `citas` WRITE;
/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
INSERT INTO `citas` VALUES (2,1,1,'2026-10-02','10:00:00','10:30:00','Pendiente','2026-09-30 19:38:00','2026-10-01 06:00:25'),(5,1,1,'2026-10-07','11:30:00','12:00:00','Pendiente','2026-10-06 21:14:59','2026-10-06 21:14:59'),(23,2,1,'2026-10-26','10:00:00','10:30:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(24,3,1,'2026-10-26','11:30:00','12:00:00','Confirmada','2026-10-26 02:33:33','2026-10-26 02:33:33'),(25,1,1,'2026-10-27','10:00:00','10:30:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(26,1,2,'2026-10-29','11:30:00','12:00:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(27,1,3,'2026-10-30','10:00:00','10:30:00','Cancelada','2026-10-26 02:33:33','2026-10-26 02:33:33'),(28,1,4,'2026-10-18','10:00:00','10:30:00','Atendida','2026-10-26 02:33:33','2026-10-26 02:33:33'),(29,7,1,'2026-10-27','11:30:00','12:00:00','Cancelada','2026-10-26 02:33:33','2026-10-26 02:33:33'),(30,4,1,'2026-10-28','11:30:00','12:00:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(31,5,1,'2026-10-22','10:00:00','10:30:00','Atendida','2026-10-26 02:33:33','2026-10-26 02:33:33'),(32,6,1,'2026-10-24','11:30:00','12:00:00','No Asistio','2026-10-26 02:33:33','2026-10-26 02:33:33'),(33,5,3,'2026-10-26','10:00:00','10:30:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(34,6,4,'2026-10-26','11:30:00','12:00:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(35,7,5,'2026-10-28','10:00:00','10:30:00','Pendiente','2026-10-26 02:33:33','2026-10-26 02:33:33'),(36,4,6,'2026-10-31','10:00:00','10:30:00','Confirmada','2026-10-26 02:33:33','2026-10-26 02:33:33');
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `especialidades`
--

DROP TABLE IF EXISTS `especialidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `especialidades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `estado` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `especialidades`
--

LOCK TABLES `especialidades` WRITE;
/*!40000 ALTER TABLE `especialidades` DISABLE KEYS */;
INSERT INTO `especialidades` VALUES (1,'Cardiologia',NULL,1,'2026-09-30 12:03:36','2026-09-30 12:03:36'),(2,'Pediatría','Atención médica de bebés, niños y adolescentes.',1,'2026-10-26 02:29:18','2026-10-26 02:29:18'),(3,'Medicina General','Consulta general, chequeos y derivación a especialistas.',1,'2026-10-26 02:29:18','2026-10-26 02:29:18'),(4,'Dermatología','Enfermedades de la piel, cabello y uñas.',1,'2026-10-26 02:29:18','2026-10-26 02:29:18'),(5,'Oftalmología','Salud visual y enfermedades de los ojos.',1,'2026-10-26 02:29:18','2026-10-26 02:29:18'),(6,'Odontología','Salud bucal y dental.',1,'2026-10-26 02:29:18','2026-10-26 02:29:18');
/*!40000 ALTER TABLE `especialidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historias_clinicas`
--

DROP TABLE IF EXISTS `historias_clinicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historias_clinicas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cita_id` int NOT NULL,
  `diagnostico` text NOT NULL,
  `tratamiento` text,
  `receta_medica` text,
  `observaciones` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cita_id` (`cita_id`),
  CONSTRAINT `historias_clinicas_ibfk_1` FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historias_clinicas`
--

LOCK TABLES `historias_clinicas` WRITE;
/*!40000 ALTER TABLE `historias_clinicas` DISABLE KEYS */;
INSERT INTO `historias_clinicas` VALUES (1,28,'Faringitis aguda.','Reposo relativo e hidratación abundante por 5 días.','Paracetamol 500 mg cada 8 horas por 3 días.\nIbuprofeno 400 mg cada 12 horas si hay dolor.','Control si la fiebre persiste más de 72 horas.','2026-10-26 03:21:59','2026-10-26 03:21:59'),(2,31,'Control cardiológico de rutina. Presión arterial y ritmo dentro de lo normal.','Mantener actividad física moderada.',NULL,'Próximo control en 6 meses.','2026-10-26 03:21:59','2026-10-26 03:21:59');
/*!40000 ALTER TABLE `historias_clinicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios_medicos`
--

DROP TABLE IF EXISTS `horarios_medicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios_medicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medico_id` int NOT NULL,
  `dia_semana` tinyint NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `duracion_consulta_minutos` int DEFAULT '30',
  `consultorio` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medico_id` (`medico_id`),
  CONSTRAINT `horarios_medicos_ibfk_1` FOREIGN KEY (`medico_id`) REFERENCES `medicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios_medicos`
--

LOCK TABLES `horarios_medicos` WRITE;
/*!40000 ALTER TABLE `horarios_medicos` DISABLE KEYS */;
INSERT INTO `horarios_medicos` VALUES (1,2,1,'08:00:00','13:00:00',30,'C-101','2026-10-26 02:29:20','2026-10-26 02:29:20'),(2,2,2,'08:00:00','13:00:00',30,'C-101','2026-10-26 02:29:20','2026-10-26 02:29:20'),(3,2,3,'08:00:00','13:00:00',30,'C-101','2026-10-26 02:29:20','2026-10-26 02:29:20'),(4,2,4,'08:00:00','13:00:00',30,'C-101','2026-10-26 02:29:20','2026-10-26 02:29:20'),(5,2,5,'08:00:00','13:00:00',30,'C-101','2026-10-26 02:29:20','2026-10-26 02:29:20'),(6,3,1,'08:00:00','13:00:00',30,'C-102','2026-10-26 02:29:20','2026-10-26 02:29:20'),(7,3,2,'08:00:00','13:00:00',30,'C-102','2026-10-26 02:29:20','2026-10-26 02:29:20'),(8,3,3,'08:00:00','13:00:00',30,'C-102','2026-10-26 02:29:20','2026-10-26 02:29:20'),(9,3,4,'08:00:00','13:00:00',30,'C-102','2026-10-26 02:29:20','2026-10-26 02:29:20'),(10,3,5,'08:00:00','13:00:00',30,'C-102','2026-10-26 02:29:20','2026-10-26 02:29:20'),(11,4,1,'08:00:00','13:00:00',30,'C-103','2026-10-26 02:29:20','2026-10-26 02:29:20'),(12,4,2,'08:00:00','13:00:00',30,'C-103','2026-10-26 02:29:20','2026-10-26 02:29:20'),(13,4,3,'08:00:00','13:00:00',30,'C-103','2026-10-26 02:29:20','2026-10-26 02:29:20'),(14,4,4,'08:00:00','13:00:00',30,'C-103','2026-10-26 02:29:20','2026-10-26 02:29:20'),(15,4,5,'08:00:00','13:00:00',30,'C-103','2026-10-26 02:29:20','2026-10-26 02:29:20'),(16,5,1,'08:00:00','13:00:00',30,'C-104','2026-10-26 02:29:20','2026-10-26 02:29:20'),(17,5,2,'08:00:00','13:00:00',30,'C-104','2026-10-26 02:29:20','2026-10-26 02:29:20'),(18,5,3,'08:00:00','13:00:00',30,'C-104','2026-10-26 02:29:20','2026-10-26 02:29:20'),(19,5,4,'08:00:00','13:00:00',30,'C-104','2026-10-26 02:29:20','2026-10-26 02:29:20'),(20,5,5,'08:00:00','13:00:00',30,'C-104','2026-10-26 02:29:20','2026-10-26 02:29:20'),(21,6,1,'08:00:00','13:00:00',30,'C-105','2026-10-26 02:29:20','2026-10-26 02:29:20'),(22,6,2,'08:00:00','13:00:00',30,'C-105','2026-10-26 02:29:20','2026-10-26 02:29:20'),(23,6,3,'08:00:00','13:00:00',30,'C-105','2026-10-26 02:29:20','2026-10-26 02:29:20'),(24,6,4,'08:00:00','13:00:00',30,'C-105','2026-10-26 02:29:20','2026-10-26 02:29:20'),(25,6,5,'08:00:00','13:00:00',30,'C-105','2026-10-26 02:29:20','2026-10-26 02:29:20'),(26,7,1,'08:00:00','13:00:00',30,'C-106','2026-10-26 02:29:20','2026-10-26 02:29:20'),(27,7,2,'08:00:00','13:00:00',30,'C-106','2026-10-26 02:29:20','2026-10-26 02:29:20'),(28,7,3,'08:00:00','13:00:00',30,'C-106','2026-10-26 02:29:20','2026-10-26 02:29:20'),(29,7,4,'08:00:00','13:00:00',30,'C-106','2026-10-26 02:29:20','2026-10-26 02:29:20'),(30,7,5,'08:00:00','13:00:00',30,'C-106','2026-10-26 02:29:20','2026-10-26 02:29:20'),(31,8,1,'08:00:00','13:00:00',30,'C-107','2026-10-26 02:29:20','2026-10-26 02:29:20'),(32,8,2,'08:00:00','13:00:00',30,'C-107','2026-10-26 02:29:20','2026-10-26 02:29:20'),(33,8,3,'08:00:00','13:00:00',30,'C-107','2026-10-26 02:29:20','2026-10-26 02:29:20'),(34,8,4,'08:00:00','13:00:00',30,'C-107','2026-10-26 02:29:20','2026-10-26 02:29:20'),(35,8,5,'08:00:00','13:00:00',30,'C-107','2026-10-26 02:29:20','2026-10-26 02:29:20'),(36,1,1,'08:00:00','13:00:00',30,'C-108','2026-10-26 02:29:20','2026-10-26 02:29:20'),(37,1,2,'08:00:00','13:00:00',30,'C-108','2026-10-26 02:29:20','2026-10-26 02:29:20'),(38,1,3,'08:00:00','13:00:00',30,'C-108','2026-10-26 02:29:20','2026-10-26 02:29:20'),(39,1,4,'08:00:00','13:00:00',30,'C-108','2026-10-26 02:29:20','2026-10-26 02:29:20'),(40,1,5,'08:00:00','13:00:00',30,'C-108','2026-10-26 02:29:20','2026-10-26 02:29:20');
/*!40000 ALTER TABLE `horarios_medicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicos`
--

DROP TABLE IF EXISTS `medicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `especialidad_id` int NOT NULL,
  `colegiatura` varchar(50) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `colegiatura` (`colegiatura`),
  KEY `especialidad_id` (`especialidad_id`),
  CONSTRAINT `medicos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medicos_ibfk_2` FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicos`
--

LOCK TABLES `medicos` WRITE;
/*!40000 ALTER TABLE `medicos` DISABLE KEYS */;
INSERT INTO `medicos` VALUES (1,2,1,'CMP12345','Carlos','Perez','2026-09-30 12:03:42','2026-09-30 12:03:42'),(2,4,1,'CMP-10001','Ana','Torres Vega','2026-10-26 02:29:18','2026-10-26 02:29:18'),(3,5,2,'CMP-10002','Jorge','Salazar Mendoza','2026-10-26 02:29:19','2026-10-26 02:29:19'),(4,6,3,'CMP-10003','Rosa','Huamán Castillo','2026-10-26 02:29:19','2026-10-26 02:29:19'),(5,7,3,'CMP-10004','Pedro','Gutiérrez Ramos','2026-10-26 02:29:19','2026-10-26 02:29:19'),(6,8,4,'CMP-10005','Lucía','Fernández Paredes','2026-10-26 02:29:19','2026-10-26 02:29:19'),(7,9,5,'CMP-10006','Miguel','Chávez Romero','2026-10-26 02:29:19','2026-10-26 02:29:19'),(8,10,6,'CMP-10007','Carmen','Flores Díaz','2026-10-26 02:29:19','2026-10-26 02:29:19');
/*!40000 ALTER TABLE `medicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pacientes`
--

DROP TABLE IF EXISTS `pacientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pacientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `dni` varchar(15) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `dni` (`dni`),
  CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pacientes`
--

LOCK TABLES `pacientes` WRITE;
/*!40000 ALTER TABLE `pacientes` DISABLE KEYS */;
INSERT INTO `pacientes` VALUES (1,1,'70000001','Mario','Quispe Rojas','987654321','2003-01-01','2026-09-30 11:51:32','2026-09-30 11:51:32'),(2,11,'45871236','Ana María','Gonzales Ruiz','987654321','1985-03-14','2026-10-26 02:29:20','2026-10-26 02:29:20'),(3,12,'70123456','José Luis','Ramírez Soto','986543210','1992-07-22','2026-10-26 02:29:20','2026-10-26 02:29:20'),(4,13,'46589123','Carla','Mendoza Vílchez','985432109','1978-11-02','2026-10-26 02:29:20','2026-10-26 02:29:20'),(5,14,'71234589','Diego Alonso','Paredes Lima','984321098','2015-05-30','2026-10-26 02:29:20','2026-10-26 02:29:20'),(6,15,'47896541','Elena','Castro Navarro','983210987','1964-01-18','2026-10-26 02:29:20','2026-10-26 02:29:20'),(7,16,'72345678','Fernando','Rojas Quiroz','982109876','1999-09-09','2026-10-26 02:29:21','2026-10-26 02:29:21'),(8,17,'48123697','Gabriela','Torres Sánchez','981098765','1988-12-25','2026-10-26 02:29:21','2026-10-26 02:29:21');
/*!40000 ALTER TABLE `pacientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Paciente','2026-09-30 11:49:27','2026-09-30 11:49:27'),(2,'Medico','2026-09-30 11:55:11','2026-09-30 11:55:11'),(3,'Administrador','2026-10-26 02:29:18','2026-10-26 02:29:18');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `correo` varchar(150) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'mario.quispe@pacientes.clinica.com','$2y$12$tiaVcDr5g7alwhuR87bvpOz0.l9T3KOK56aTFpK68ftuLpodHVljq',1,'2026-09-30 11:51:23','2026-09-30 11:51:23'),(2,2,'carlos.perez@clinica.com','$2y$12$tiaVcDr5g7alwhuR87bvpOz0.l9T3KOK56aTFpK68ftuLpodHVljq',1,'2026-09-30 11:55:17','2026-10-17 09:45:22'),(3,3,'admin@clinica.com','$2y$12$tiaVcDr5g7alwhuR87bvpOz0.l9T3KOK56aTFpK68ftuLpodHVljq',1,'2026-10-17 05:15:25','2026-10-26 02:29:18'),(4,2,'ana.torres@clinica.com','$2y$12$/FCC4MfuqRCXBEmPjsTIyuY8G0M38P679YLO/RlYhYQErTL1C9k2e',1,'2026-10-26 02:29:18','2026-10-26 02:29:18'),(5,2,'jorge.salazar@clinica.com','$2y$12$5HgsX1NzRNaWX6o5FDqOiOPJAlTcOe4rj18sfhrvKbwmna6IXMdiC',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(6,2,'rosa.huaman@clinica.com','$2y$12$bjcqmgZBPEWJTtGnv8Dqiu2WTEcj1iGOv//9J.Ky6IGoWYZ4ejfqO',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(7,2,'pedro.gutierrez@clinica.com','$2y$12$tKD6w3UZp9LPLBL4Bahreey6EMPhxCix9hQxeSg.UgTn122Bam/Fq',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(8,2,'lucia.fernandez@clinica.com','$2y$12$.o.Y7nNqWT0gejOVxgiI.uT3PG0qY6sUzCjtLfjHeAw/qbddVFz8K',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(9,2,'miguel.chavez@clinica.com','$2y$12$n7JDvYCzAdVqCjA3Z/Quj.Pe9lDqd8Y5.mWy.UKtDMvfKghDU2oYO',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(10,2,'carmen.flores@clinica.com','$2y$12$/jXcIIBDcwh7uycwQGwWE.DMMqJV3yXkxlZzs3ifBHNLo6.AO3ST2',1,'2026-10-26 02:29:19','2026-10-26 02:29:19'),(11,1,'ana.gonzales@pacientes.clinica.com','$2y$12$Kq0CZLByL5rI6gVxN6jvPO/1CY5Lwi5yRfQW02MfOJEPkEXPkRJCu',1,'2026-10-26 02:29:20','2026-10-26 02:29:20'),(12,1,'jose.ramirez@pacientes.clinica.com','$2y$12$TggN0c3y.iyf2vvW.pvzU.275ViYoKN8MYvpKpcsl1nD0guGw3u.O',1,'2026-10-26 02:29:20','2026-10-26 02:29:20'),(13,1,'carla.mendoza@pacientes.clinica.com','$2y$12$2Lno/RCeyPBysDGQo0bac.nV5QinC6sbph6sDy5Z/fbepK/CD5.QW',1,'2026-10-26 02:29:20','2026-10-26 02:29:20'),(14,1,'diego.paredes@pacientes.clinica.com','$2y$12$PLMtKhZtLkQbaVuZ6cFmL.Ito/8KYbP5gcVmDmu8irmgxF4/7RYSW',1,'2026-10-26 02:29:20','2026-10-26 02:29:20'),(15,1,'elena.castro@pacientes.clinica.com','$2y$12$/I7I3Thqsz061bSrsOIsweVSR/ing33l.lNbPInSGgepWviMGaPUa',1,'2026-10-26 02:29:20','2026-10-26 02:29:20'),(16,1,'fernando.rojas@pacientes.clinica.com','$2y$12$GLdM1OOIXqhCeAF8z7tEFO34DXVyJHFrm9xAd4fjbqriZJnqEwU/a',1,'2026-10-26 02:29:21','2026-10-26 02:29:21'),(17,1,'gabriela.torres@pacientes.clinica.com','$2y$12$QUxmIicV4igOutqzGbbF/.vAoQ1rG9KNxBtNHjS4XbmnDdsP22Z3u',0,'2026-10-26 02:29:21','2026-10-26 02:29:21');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26 22:25:27
