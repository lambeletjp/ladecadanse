-- Migration: Add 'pause' status to lieu and organisateur tables
-- This status is for entities that are temporarily inactive (e.g. seasonal venues)
-- When an entity with 'pause' status receives a new event, it's automatically set back to 'actif'

ALTER TABLE `lieu` 
MODIFY COLUMN `statut` enum('actif','pause','inactif','ancien') COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `organisateur` 
MODIFY COLUMN `statut` enum('actif','pause','inactif','ancien') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif';

