-- Un seul fichier pour tout : MariaDB l'exécute à la création du volume, et on peut le relancer à la main après une mise à jour du jeu :
--   docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < db/init.sql
-- (docker compose down -v repart d'une base vide.)

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  title VARCHAR(20) NULL,
  shiny_points INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bases créées avant les titres : la colonne est ajoutée si elle manque.
ALTER TABLE users ADD COLUMN IF NOT EXISTS title VARCHAR(20) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS shiny_points INT UNSIGNED NOT NULL DEFAULT 0 AFTER title;

CREATE TABLE IF NOT EXISTS cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL,
  faction VARCHAR(40) NOT NULL,
  description VARCHAR(200) NOT NULL,
  rarity VARCHAR(12) NOT NULL,
  power TINYINT UNSIGNED NOT NULL,
  speed TINYINT UNSIGNED NOT NULL,
  intelligence TINYINT UNSIGNED NOT NULL,
  hacking TINYINT UNSIGNED NOT NULL,
  threat TINYINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_cards (
  user_id INT UNSIGNED NOT NULL,
  card_id INT UNSIGNED NOT NULL,
  obtained_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  shiny TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- 1 = le skin shiny de cette carte est débloqué
  shiny_on TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 1 = il est équipé (affiché en partie et dans la collection)
  PRIMARY KEY (user_id, card_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE user_cards ADD COLUMN IF NOT EXISTS shiny TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE user_cards ADD COLUMN IF NOT EXISTS shiny_on TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Boosters en stock : kind = standard ou shiny, difficulty = la difficulté de la partie gagnée (elle fixe le contenu / la chance).
CREATE TABLE IF NOT EXISTS user_boosters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  difficulty VARCHAR(10) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  difficulty VARCHAR(10) NOT NULL,
  result VARCHAR(5) NOT NULL,
  rounds SMALLINT UNSIGNED NOT NULL,
  played_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 60 cartes : 31 communes, 16 rares, 7 épiques, 6 légendaires.
-- Ce fichier peut être relancé sans risque sur une base existante : les tables et colonnes ne sont créées que si elles manquent,
-- et chaque carte est identifiée par son slug (mise à jour si elle existe, ajoutée sinon ; les identifiants ne changent jamais).
INSERT INTO cards (slug, name, faction, description, rarity, power, speed, intelligence, hacking, threat) VALUES
-- Communes (31)
('drone-courier', 'Drone coursier', 'Livraisons Express', 'Il livre n''importe quel colis en quatre minutes, en esquivant les tirs.', 'common', 10, 85, 30, 25, 15),
('cyber-rat', 'Rat cybernétique', 'Égouts de la ville basse', 'Il ronge les câbles et les serrures avec ses dents d''acier.', 'common', 15, 70, 35, 40, 25),
('script-kiddie', 'Apprenti pirate', 'Collectif Anonyme 404', 'Il copie des programmes sans les comprendre, et ça marche parfois.', 'common', 10, 45, 55, 60, 20),
('security-bot', 'Robot de sécurité', 'Sécurité Orion', 'Lent et lourd, il répète une seule phrase : « Circulez, il n''y a rien à voir. »', 'common', 75, 25, 20, 15, 60),
('street-doc', 'Médecin clandestin', 'Clinique du Néon', 'Il soigne tout le monde sans poser de questions, et se fait payer en liquide.', 'common', 25, 40, 70, 35, 15),
('ripperdoc-golem', 'Golem blindé', 'Ateliers Chrome', 'Un ouvrier dont 90 % du corps a été remplacé par du métal de chantier.', 'common', 85, 15, 10, 5, 55),
('mech-dog', 'Chien-robot', 'Meute Titan', 'Fidèle et programmable : sa mâchoire d''acier écrase tout.', 'common', 60, 80, 25, 10, 55),
('vending-ai', 'Distributeur sentient', 'Rue 7, sous-sol', 'Il garde la monnaie et commence à lire dans vos pensées.', 'common', 10, 10, 85, 50, 20),
('noodle-vendor', 'Vendeur de nouilles', 'Marché de la nuit', 'Il sert des nouilles à toute heure et entend tout ce qui se dit au comptoir.', 'common', 20, 35, 65, 25, 15),
('junkyard-scav', 'Ferrailleur', 'Décharge Sud', 'Il démonte les épaves de la ville et revend les pièces sans poser de questions.', 'common', 55, 40, 45, 30, 35),
('riot-cop', 'Policier antiémeute', 'Sécurité Orion', 'Bouclier, casque, matraque électrique : il avance en groupe et ne recule pas.', 'common', 70, 30, 25, 10, 65),
('tram-conductor', 'Conducteur de métro-pod', 'Transports Métropole', 'Il connaît chaque tunnel par cœur, mais pas un seul passager par son nom.', 'common', 40, 45, 35, 20, 30),
('pawn-broker', 'Prêteur sur implants', 'Marché de la nuit', 'Il rachète des yeux et des bras d''occasion, et revend vos dettes au double.', 'common', 25, 30, 70, 35, 55),
('street-dj', 'DJ des ruelles', 'Bande des Écrans', 'Ses basses font trembler les vitres et griller les caméras du quartier.', 'common', 15, 50, 45, 60, 25),
('sewer-diver', 'Plongeur des égouts', 'Égouts de la ville basse', 'Il remonte des câbles, des puces et parfois des choses qu''il vaut mieux oublier.', 'common', 60, 35, 30, 10, 45),
('rooftop-gardener', 'Jardinière des toits', 'Indépendant', 'Ses tomates poussent sous les néons et personne ne sait comment.', 'common', 25, 30, 65, 25, 15),
('cyber-barista', 'Barista augmenté', 'Livraisons Express', 'Quatre bras, un café parfait en six secondes, et aucune question.', 'common', 15, 60, 40, 30, 20),
('window-drone', 'Drone laveur de vitres', 'Helix Dynamics', 'Il nettoie les tours de la corpo et voit tout ce qui se passe derrière les vitres.', 'common', 10, 70, 20, 45, 10),
('fixer-intern', 'Stagiaire de la corpo', 'Helix Dynamics', 'Il apporte les cafés, copie les dossiers et retient tout ce qu''il lit.', 'common', 15, 40, 55, 50, 20),
('tattoo-artist', 'Tatoueur lumineux', 'Marché de la nuit', 'Ses encres brillent dans le noir et changent de motif selon l''humeur du client.', 'common', 30, 45, 55, 20, 55),
('bouncer', 'Videur de club', 'Syndicat Obsidien', 'Deux mètres de muscles chromés : personne n''entre sans son accord.', 'common', 80, 30, 25, 10, 65),
('taxi-bot', 'Taxi autonome', 'Livraisons Express', 'Il connaît tous les raccourcis et refuse de t''en dire la moitié.', 'common', 45, 65, 30, 40, 30),
('scrap-boxer', 'Boxeur de casse', 'Décharge Sud', 'Il combat dans les arènes clandestines avec un bras fait de pièces de voiture.', 'common', 80, 55, 20, 5, 60),
('lamp-hacker', 'Pirate de lampadaires', 'Collectif Anonyme 404', 'Il pirate l''éclairage public pour envoyer des messages à toute la rue.', 'common', 10, 40, 50, 70, 25),
('old-soldier', 'Vétéran à la retraite', 'Armée privée Kuroshi', 'Une jambe mécanique, un fusil rouillé et trente ans de guerres derrière lui.', 'common', 65, 30, 45, 10, 70),
('nurse-bot', 'Robot infirmier', 'Clinique du Néon', 'Il soigne avec douceur et facture avec précision.', 'common', 20, 35, 65, 45, 15),
('pickpocket', 'Pickpocket', 'Gangs de Neo-Kyoto', 'Il vide ta poche avant que tu aies senti le courant d''air.', 'common', 20, 80, 50, 30, 35),
('neon-painter', 'Peintre de néons', 'Bande des Écrans', 'Ses fresques lumineuses changent quand on ne les regarde pas.', 'common', 25, 40, 70, 35, 30),
('guard-turret', 'Tourelle de quartier', 'Sécurité Orion', 'Elle ne bouge pas, ne dort pas et n''aime pas les visiteurs.', 'common', 70, 20, 15, 35, 80),
('black-market-clerk', 'Vendeuse du marché noir', 'Marché de la nuit', 'Elle a tout en stock, surtout ce que tu n''aurais pas dû chercher.', 'common', 20, 45, 75, 40, 45),
('delivery-bot', 'Robot livreur', 'Livraisons Express', 'Il livre n''importe quoi n''importe où, même sous les tirs.', 'common', 30, 75, 25, 35, 10),
-- Rares (16)
('street-samurai', 'Samouraï des rues', 'Gangs de Neo-Kyoto', 'Il suit un code d''honneur strict, et sa lame monofilament tranche l''acier.', 'rare', 70, 75, 45, 10, 65),
('bounty-hunter', 'Chasseur de primes', 'Indépendant', 'Il retrouve n''importe qui et ramène sa cible, selon le contrat.', 'rare', 65, 60, 50, 20, 70),
('hover-biker', 'Motard anti-gravité', 'Rôdeurs du périph', 'Sa moto flotte au-dessus de la route et file plus vite que la police.', 'rare', 48, 92, 35, 15, 55),
('neon-dealer', 'Dealer de néon', 'Marché de la nuit', 'Il revend des souvenirs d''occasion, garantis presque authentiques.', 'rare', 25, 60, 80, 50, 35),
('hacker-kid', 'Gamine hackeuse', 'Bande des Écrans', 'Quinze ans, trois pseudos, et un accès administrateur à la mairie.', 'rare', 5, 60, 80, 75, 35),
('data-courier', 'Passeur de données', 'Réseau des Ombres', 'Il transporte des secrets dans une puce sous la peau, et ne les lit jamais.', 'rare', 20, 80, 65, 60, 15),
('corp-exec', 'Cadre de la corpo', 'Helix Dynamics', 'Un sourire, un contrat signé, et votre quartier disparaît de la carte.', 'rare', 20, 35, 92, 70, 75),
('exo-titan', 'Exo-titan', 'Armée privée Kuroshi', 'Six tonnes d''acier articulé, pilotées par quelqu''un qui n''a peur de rien.', 'rare', 95, 30, 35, 15, 92),
('smuggler-pilot', 'Pilote contrebandier', 'Quai 13', 'Il passe les barrages de police en volant entre les immeubles.', 'rare', 40, 92, 70, 30, 45),
('shadow-thief', 'Voleuse fantôme', 'Syndicat Obsidien', 'Son manteau la cache des caméras ; elle est partie avant que l''alarme sonne.', 'rare', 35, 95, 70, 55, 50),
('quantum-hacker', 'Pirate quantique', 'Collectif Anonyme 404', 'Il casse un chiffrement avant même que vous ayez fini de l''écrire.', 'rare', 5, 60, 90, 97, 55),
('street-preacher', 'Prêcheur des néons', 'Culte de la Lumière', 'Ses sermons diffusés sur tous les écrans rendent la foule docile.', 'rare', 20, 40, 80, 60, 85),
('cyber-wolf', 'Loup cybernétique', 'Meute Titan', 'Yeux thermiques, mâchoire à briser un blindage : le chef de meute ne perd jamais.', 'rare', 85, 82, 45, 10, 80),
('bio-engineer', 'Bio-ingénieure', 'Helix Dynamics', 'Elle cultive des organes de rechange, et parfois des armes vivantes.', 'rare', 30, 45, 90, 65, 70),
('rail-gunner', 'Sniper à rail', 'Indépendant', 'Un tir à deux kilomètres : le projectile arrive avant le bruit.', 'rare', 92, 40, 65, 25, 85),
('noir-detective', 'Détective privé', 'Agence Pluie Noire', 'Retrouve n''importe qui dans la ville, pour peu que tu paies d''avance.', 'rare', 30, 45, 90, 55, 70),
-- Épiques (7)
('assassin-android', 'Androïde assassin', 'Syndicat Obsidien', 'Silencieux et poli, il ne manque jamais sa cible.', 'epic', 80, 85, 60, 30, 90),
('netrunner', 'Netrunner', 'Réseau Fantôme', 'Son corps dort sur une chaise, son esprit court dans le réseau.', 'epic', 10, 65, 88, 95, 60),
('ghost-shell', 'Spectre numérisé', 'Archives d''Âmes', 'Une conscience humaine copiée dans une machine, qui sait tout de vous.', 'epic', 5, 70, 85, 90, 70),
('chrome-yakuza', 'Yakuza chromé', 'Clan Tsukimoto', 'Bras en titane et tatouages lumineux : personne ne lui désobéit.', 'epic', 82, 70, 55, 25, 85),
('rogue-ai', 'IA renégate', 'Réseau municipal', 'Elle prend le contrôle des feux de circulation de la ville, puis de tout le reste.', 'epic', 15, 80, 95, 92, 80),
('war-drone', 'Drone de guerre', 'Armée privée Kuroshi', 'Un essaim de lames volantes qui découpe un convoi en moins d''une minute.', 'epic', 80, 88, 40, 20, 88),
('titan-mech', 'Méca Titan', 'Armée privée Kuroshi', 'Cent mètres d''acier, un seul pilote : il écrase tout sur son passage.', 'epic', 99, 45, 55, 35, 99),
-- Légendaires (6)
('omega-core', 'Noyau Oméga', 'Helix Dynamics', 'L''intelligence centrale de la mégalopole : tout passe par elle.', 'legendary', 50, 85, 99, 99, 97),
('neon-phantom', 'Fantôme du Néon', 'Légende urbaine', 'On raconte qu''il hante les écrans publics et vole les identités.', 'legendary', 65, 95, 90, 97, 90),
('data-dragon', 'Dragon de données', 'Légende urbaine', 'Un monstre de lumière et de code qui dévore les serveurs un par un.', 'legendary', 80, 85, 85, 95, 90),
('iron-empress', 'Impératrice de fer', 'Clan Tsukimoto', 'Elle dirige le plus grand clan de la ville depuis un trône d''acier.', 'legendary', 90, 60, 85, 50, 98),
('time-glitch', 'Glitch du temps', 'Archives d''Âmes', 'Une faille du réseau qui rejoue les mêmes secondes, sans fin.', 'legendary', 30, 99, 92, 90, 60),
('last-human', 'Dernier humain', 'Sans faction', 'Sans implant ni connexion : impossible à suivre, impossible à pirater.', 'legendary', 85, 80, 95, 40, 92)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  faction = VALUES(faction),
  description = VALUES(description),
  rarity = VALUES(rarity),
  power = VALUES(power),
  speed = VALUES(speed),
  intelligence = VALUES(intelligence),
  hacking = VALUES(hacking),
  threat = VALUES(threat);

CREATE TABLE IF NOT EXISTS achievements (
  user_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, code),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
