-- Fixtures pour les tests Selenium
-- Lieu de test pour le test lieu-salle-edit

INSERT INTO `lieu` (`idLieu`, `idpersonne`, `statut`, `nom`, `determinant`, `adresse`, `quartier`, `localite_id`, `region`, `lat`, `lng`, `categorie`, `horaire_general`, `photo1`, `photo2`, `logo`, `URL`, `actif`, `dateAjout`, `date_derniere_modif`) VALUES
(999, 1, 'actif', 'Lieu Test Selenium', 'au', 'Rue du Test 42', 'Plainpalais', 44, 'ge', 0, 0, 'salle', 'Lun-Ven 9h-18h', '', '', '', 'https://test.local', 1, NOW(), NOW());


