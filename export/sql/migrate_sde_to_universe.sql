-- ============================================================================
-- SDE to Universe Database Migration Script
-- ============================================================================
-- This script migrates static EVE Online data from the SDE (Static Data Export)
-- database to the Pathfinder Universe database schema.
--
-- This script is IDEMPOTENT - it can be run multiple times safely.
-- - First run: Inserts new data
-- - Subsequent runs: Updates existing data with fresh SDE values
--
-- BEFORE RUNNING:
-- 1. Backup your Universe database (recommended)
-- 2. Verify both databases are accessible
-- 3. Configure database names below if different from defaults
-- 4. Test on a copy first!
--
-- CONFIGURATION: Update these database names to match your setup
-- ============================================================================

-- Configure your database names here
SET @sde_db = 'eve_sde';
SET @universe_db = 'universe';

-- ============================================================================
-- PREPARATION
-- ============================================================================

-- Disable foreign key checks for bulk loading
SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;
SET UNIQUE_CHECKS = 0;

-- Start transaction
START TRANSACTION;

-- ============================================================================
-- PHASE 1: REFERENCE DATA (No Dependencies)
-- ============================================================================

-- 1.1 Region
SELECT 'Migrating regions...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.region (id, created, updated, name, description)
SELECT
    regionID,
    NOW(),
    NOW(),
    regionName,
    NULL
FROM ', @sde_db, '.mapRegions
WHERE regionName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    description = VALUES(description)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.2 Faction
SELECT 'Migrating factions...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.faction (id, created, updated, name, description, sizeFactor, stationCount, stationSystemCount)
SELECT
    factionID,
    NOW(),
    NOW(),
    factionName,
    description,
    COALESCE(sizeFactor, 0),
    COALESCE(stationCount, 0),
    COALESCE(stationSystemCount, 0)
FROM ', @sde_db, '.chrFactions
WHERE factionName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    description = VALUES(description),
    sizeFactor = VALUES(sizeFactor),
    stationCount = VALUES(stationCount),
    stationSystemCount = VALUES(stationSystemCount)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.3 Race
SELECT 'Migrating races...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.race (id, created, updated, name, description, factionId)
SELECT
    raceID,
    NOW(),
    NOW(),
    raceName,
    description,
    NULL
FROM ', @sde_db, '.chrRaces
WHERE raceName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    description = VALUES(description),
    factionId = VALUES(factionId)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.4 Category
SELECT 'Migrating categories...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.category (id, created, updated, name, published)
SELECT
    categoryID,
    NOW(),
    NOW(),
    categoryName,
    COALESCE(published, 1)
FROM ', @sde_db, '.invCategories
WHERE published = 1 AND categoryName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    published = VALUES(published)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1.5 Group
SELECT 'Migrating groups...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.`group` (id, created, updated, name, published, categoryId)
SELECT
    groupID,
    NOW(),
    NOW(),
    groupName,
    COALESCE(published, 1),
    categoryID
FROM ', @sde_db, '.invGroups
WHERE published = 1 AND groupName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    published = VALUES(published),
    categoryId = VALUES(categoryId)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PHASE 2: TYPE SYSTEM
-- ============================================================================

-- 2.1 Type
SELECT 'Migrating types (this may take a while)...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.type (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, marketGroupId, packagedVolume, portionSize, graphicId)
SELECT
    typeID,
    NOW(),
    NOW(),
    typeName,
    description,
    COALESCE(published, 1),
    0,
    COALESCE(volume, 0),
    COALESCE(capacity, 0),
    COALESCE(mass, 0),
    groupID,
    COALESCE(marketGroupID, 0),
    0,
    COALESCE(portionSize, 0),
    COALESCE(graphicID, 0)
FROM ', @sde_db, '.invTypes
WHERE (published = 1 OR groupID IN (15, 1932, 1025, 365, 2017))
  AND typeName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    description = VALUES(description),
    published = VALUES(published),
    radius = VALUES(radius),
    volume = VALUES(volume),
    capacity = VALUES(capacity),
    mass = VALUES(mass),
    groupId = VALUES(groupId),
    marketGroupId = VALUES(marketGroupId),
    packagedVolume = VALUES(packagedVolume),
    portionSize = VALUES(portionSize),
    graphicId = VALUES(graphicId)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.2 Dogma Attribute
SELECT 'Migrating dogma attributes...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.dogma_attribute (id, created, updated, name, displayName, description, published, stackable, highIsGood, defaultValue, iconId, unitId)
SELECT
    attributeID,
    NOW(),
    NOW(),
    attributeName,
    displayName,
    description,
    COALESCE(published, 1),
    COALESCE(stackable, 0),
    COALESCE(highIsGood, 0),
    COALESCE(defaultValue, 0),
    iconID,
    unitID
FROM ', @sde_db, '.dgmAttributeTypes
WHERE published = 1 AND attributeName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    displayName = VALUES(displayName),
    description = VALUES(description),
    published = VALUES(published),
    stackable = VALUES(stackable),
    highIsGood = VALUES(highIsGood),
    defaultValue = VALUES(defaultValue),
    iconId = VALUES(iconId),
    unitId = VALUES(unitId)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.3 Type Attribute
SELECT 'Migrating type attributes (this will take several minutes)...' AS status;

SET @sql = CONCAT('
REPLACE INTO ', @universe_db, '.type_attribute (typeId, attributeId, value)
SELECT
    typeID,
    attributeID,
    COALESCE(valueFloat, valueInt, 0)
FROM ', @sde_db, '.dgmTypeAttributes
WHERE typeID IN (SELECT id FROM ', @universe_db, '.type)
  AND attributeID IN (SELECT id FROM ', @universe_db, '.dogma_attribute)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PHASE 3: SPATIAL HIERARCHY
-- ============================================================================

-- 3.1 Constellation
SELECT 'Migrating constellations...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.constellation (id, created, updated, name, regionId, x, y, z)
SELECT
    constellationID,
    NOW(),
    NOW(),
    constellationName,
    regionID,
    COALESCE(CAST(x AS SIGNED), 0),
    COALESCE(CAST(y AS SIGNED), 0),
    COALESCE(CAST(z AS SIGNED), 0)
FROM ', @sde_db, '.mapConstellations
WHERE constellationName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    regionId = VALUES(regionId),
    x = VALUES(x),
    y = VALUES(y),
    z = VALUES(z)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3.2 Star
SELECT 'Migrating stars...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.star (id, created, updated, name, typeId, age, radius, temperature, luminosity, spectralClass)
SELECT
    md.itemID,
    NOW(),
    NOW(),
    md.itemName,
    md.typeID,
    NULL,
    CAST(COALESCE(md.radius, 0) AS SIGNED),
    CAST(COALESCE(mcs.temperature, 0) AS SIGNED),
    mcs.luminosity,
    mcs.spectralClass
FROM ', @sde_db, '.mapDenormalize md
LEFT JOIN ', @sde_db, '.mapCelestialStatistics mcs ON md.itemID = mcs.celestialID
WHERE md.groupID = 6 AND md.itemName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    typeId = VALUES(typeId),
    age = VALUES(age),
    radius = VALUES(radius),
    temperature = VALUES(temperature),
    luminosity = VALUES(luminosity),
    spectralClass = VALUES(spectralClass)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3.3 System (CRITICAL TABLE)
SELECT 'Migrating systems...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.system (id, created, updated, name, constellationId, starId, security, trueSec, securityStatus, securityClass, effect, x, y, z)
SELECT
    ss.solarSystemID,
    NOW(),
    NOW(),
    ss.solarSystemName,
    ss.constellationID,
    (SELECT itemID FROM ', @sde_db, '.mapDenormalize WHERE solarSystemID = ss.solarSystemID AND groupID = 6 LIMIT 1),
    CASE
        WHEN ss.security >= 0.5 THEN \'H\'
        WHEN ss.security > 0.0 THEN \'L\'
        WHEN mc.regionID = 10000070 THEN \'T\'
        WHEN mc.regionID >= 11000000 AND mc.regionID < 12000000 AND wh.wormholeClassID BETWEEN 1 AND 6 THEN CONCAT(\'C\', wh.wormholeClassID)
        WHEN mc.regionID >= 11000000 AND mc.regionID < 12000000 AND wh.wormholeClassID = 12 THEN \'C12\'
        WHEN mc.regionID >= 11000000 AND mc.regionID < 12000000 AND wh.wormholeClassID = 13 THEN \'C13\'
        WHEN mc.regionID >= 11000000 AND mc.regionID < 12000000 AND wh.wormholeClassID BETWEEN 14 AND 18 THEN CONCAT(\'C\', wh.wormholeClassID)
        ELSE \'0.0\'
    END,
    ROUND(ss.security, 1),
    ss.security,
    ss.securityClass,
    wh.wormholeEffect,
    COALESCE(CAST(ss.x AS SIGNED), 0),
    COALESCE(CAST(ss.y AS SIGNED), 0),
    COALESCE(CAST(ss.z AS SIGNED), 0)
FROM ', @sde_db, '.mapSolarSystems ss
LEFT JOIN ', @sde_db, '.mapConstellations mc ON ss.constellationID = mc.constellationID
LEFT JOIN (
    SELECT locationID,
           wormholeClassID,
           CASE wormholeClassID
               WHEN 1 THEN \'magnetar\'
               WHEN 2 THEN \'red_giant\'
               WHEN 3 THEN \'pulsar\'
               WHEN 4 THEN \'wolf_rayet\'
               WHEN 5 THEN \'cataclysmic_variable\'
               WHEN 6 THEN \'black_hole\'
           END as wormholeEffect
    FROM ', @sde_db, '.mapLocationWormholeClasses
) wh ON mc.regionID = wh.locationID
WHERE ss.solarSystemName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    constellationId = VALUES(constellationId),
    starId = VALUES(starId),
    security = VALUES(security),
    trueSec = VALUES(trueSec),
    securityStatus = VALUES(securityStatus),
    securityClass = VALUES(securityClass),
    effect = VALUES(effect),
    x = VALUES(x),
    y = VALUES(y),
    z = VALUES(z)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PHASE 4: NPC CORPORATIONS
-- ============================================================================

-- 4.1 Corporation (NPC only)
SELECT 'Migrating NPC corporations...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.corporation (id, created, updated, name, ticker, dateFounded, memberCount, isNPC, factionId, allianceId)
SELECT
    npc.corporationID,
    NOW(),
    NOW(),
    inv.itemName,
    \'\',
    NULL,
    0,
    1,
    npc.factionID,
    NULL
FROM ', @sde_db, '.crpNPCCorporations npc
INNER JOIN ', @sde_db, '.invNames inv ON npc.corporationID = inv.itemID
WHERE inv.itemName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    ticker = VALUES(ticker),
    dateFounded = VALUES(dateFounded),
    memberCount = VALUES(memberCount),
    isNPC = VALUES(isNPC),
    factionId = VALUES(factionId),
    allianceId = VALUES(allianceId)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PHASE 5: SPATIAL OBJECTS
-- ============================================================================
-- IMPORTANT: Before migrating spatial objects (planets, stars, stargates, stations),
-- we must ensure their referenced types exist in the type table. Some of these types
-- are unpublished in SDE but are still required for proper foreign key relationships.
-- ============================================================================

-- 5.1a Planet Types (ensure all planet types exist)
SELECT 'Ensuring planet types exist...' AS status;

SET @sql = CONCAT('
INSERT IGNORE INTO ', @universe_db, '.type (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, marketGroupId, packagedVolume, portionSize, graphicId)
SELECT DISTINCT
    t.typeID,
    NOW(),
    NOW(),
    t.typeName,
    t.description,
    COALESCE(t.published, 1),
    0,
    COALESCE(t.volume, 0),
    COALESCE(t.capacity, 0),
    COALESCE(t.mass, 0),
    t.groupID,
    COALESCE(t.marketGroupID, 0),
    0,
    COALESCE(t.portionSize, 0),
    COALESCE(t.graphicID, 0)
FROM ', @sde_db, '.mapDenormalize md
INNER JOIN ', @sde_db, '.invTypes t ON md.typeID = t.typeID
WHERE md.groupID = 7 AND t.typeName IS NOT NULL');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.1b Star Types (ensure all star types exist)
SELECT 'Ensuring star types exist...' AS status;

SET @sql = CONCAT('
INSERT IGNORE INTO ', @universe_db, '.type (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, marketGroupId, packagedVolume, portionSize, graphicId)
SELECT DISTINCT
    t.typeID,
    NOW(),
    NOW(),
    t.typeName,
    t.description,
    COALESCE(t.published, 1),
    0,
    COALESCE(t.volume, 0),
    COALESCE(t.capacity, 0),
    COALESCE(t.mass, 0),
    t.groupID,
    COALESCE(t.marketGroupID, 0),
    0,
    COALESCE(t.portionSize, 0),
    COALESCE(t.graphicID, 0)
FROM ', @sde_db, '.mapDenormalize md
INNER JOIN ', @sde_db, '.invTypes t ON md.typeID = t.typeID
WHERE md.groupID = 6 AND t.typeName IS NOT NULL');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.1c Stargate Types (ensure all stargate types exist)
SELECT 'Ensuring stargate types exist...' AS status;

SET @sql = CONCAT('
INSERT IGNORE INTO ', @universe_db, '.type (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, marketGroupId, packagedVolume, portionSize, graphicId)
SELECT DISTINCT
    t.typeID,
    NOW(),
    NOW(),
    t.typeName,
    t.description,
    COALESCE(t.published, 1),
    0,
    COALESCE(t.volume, 0),
    COALESCE(t.capacity, 0),
    COALESCE(t.mass, 0),
    t.groupID,
    COALESCE(t.marketGroupID, 0),
    0,
    COALESCE(t.portionSize, 0),
    COALESCE(t.graphicID, 0)
FROM ', @sde_db, '.mapDenormalize md
INNER JOIN ', @sde_db, '.invTypes t ON md.typeID = t.typeID
WHERE md.groupID = 10 AND t.typeName IS NOT NULL');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.1 Planet
SELECT 'Migrating planets...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.planet (id, created, updated, name, systemId, typeId, x, y, z)
SELECT
    md.itemID,
    NOW(),
    NOW(),
    md.itemName,
    md.solarSystemID,
    md.typeID,
    COALESCE(CAST(md.x AS SIGNED), 0),
    COALESCE(CAST(md.y AS SIGNED), 0),
    COALESCE(CAST(md.z AS SIGNED), 0)
FROM ', @sde_db, '.mapDenormalize md
WHERE md.groupID = 7 AND md.itemName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    systemId = VALUES(systemId),
    typeId = VALUES(typeId),
    x = VALUES(x),
    y = VALUES(y),
    z = VALUES(z)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.2 Stargate
SELECT 'Migrating stargates...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.stargate (id, created, updated, name, systemId, typeId, destinationSystemId, x, y, z)
SELECT
    md.itemID,
    NOW(),
    NOW(),
    md.itemName,
    md.solarSystemID,
    md.typeID,
    dest_gate.solarSystemID,
    COALESCE(CAST(md.x AS SIGNED), 0),
    COALESCE(CAST(md.y AS SIGNED), 0),
    COALESCE(CAST(md.z AS SIGNED), 0)
FROM ', @sde_db, '.mapDenormalize md
INNER JOIN ', @sde_db, '.mapJumps mj ON md.itemID = mj.stargateID
INNER JOIN ', @sde_db, '.mapDenormalize dest_gate ON dest_gate.itemID = mj.destinationID
WHERE md.groupID = 10 AND md.itemName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    systemId = VALUES(systemId),
    typeId = VALUES(typeId),
    destinationSystemId = VALUES(destinationSystemId),
    x = VALUES(x),
    y = VALUES(y),
    z = VALUES(z)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.3 Station Types (ensure all station types exist before migrating stations)
SELECT 'Ensuring station types exist...' AS status;

SET @sql = CONCAT('
INSERT IGNORE INTO ', @universe_db, '.type (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, marketGroupId, packagedVolume, portionSize, graphicId)
SELECT DISTINCT
    t.typeID,
    NOW(),
    NOW(),
    t.typeName,
    t.description,
    COALESCE(t.published, 1),
    0,
    COALESCE(t.volume, 0),
    COALESCE(t.capacity, 0),
    COALESCE(t.mass, 0),
    t.groupID,
    COALESCE(t.marketGroupID, 0),
    0,
    COALESCE(t.portionSize, 0),
    COALESCE(t.graphicID, 0)
FROM ', @sde_db, '.staStations ss
INNER JOIN ', @sde_db, '.invTypes t ON ss.stationTypeID = t.typeID
WHERE t.typeName IS NOT NULL');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5.4 Station
SELECT 'Migrating stations...' AS status;

SET @sql = CONCAT('
INSERT INTO ', @universe_db, '.station (id, created, updated, name, systemId, typeId, corporationId, raceId, services, x, y, z)
SELECT
    ss.stationID,
    NOW(),
    NOW(),
    ss.stationName,
    ss.solarSystemID,
    ss.stationTypeID,
    ss.corporationID,
    NULL,
    NULL,
    COALESCE(CAST(ss.x AS SIGNED), 0),
    COALESCE(CAST(ss.y AS SIGNED), 0),
    COALESCE(CAST(ss.z AS SIGNED), 0)
FROM ', @sde_db, '.staStations ss
WHERE ss.stationName IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated = NOW(),
    name = VALUES(name),
    systemId = VALUES(systemId),
    typeId = VALUES(typeId),
    corporationId = VALUES(corporationId),
    raceId = VALUES(raceId),
    services = VALUES(services),
    x = VALUES(x),
    y = VALUES(y),
    z = VALUES(z)');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- FINALIZATION
-- ============================================================================

SELECT 'Migration complete! Committing transaction...' AS status;

-- Commit the transaction
COMMIT;

-- Re-enable checks
SET FOREIGN_KEY_CHECKS = 1;
SET UNIQUE_CHECKS = 1;
SET AUTOCOMMIT = 1;

-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT 'Verifying migration results...' AS status;

SET @sql = CONCAT('
SELECT \'region\' as table_name, COUNT(*) as row_count FROM ', @universe_db, '.region
UNION ALL
SELECT \'faction\', COUNT(*) FROM ', @universe_db, '.faction
UNION ALL
SELECT \'race\', COUNT(*) FROM ', @universe_db, '.race
UNION ALL
SELECT \'category\', COUNT(*) FROM ', @universe_db, '.category
UNION ALL
SELECT \'group\', COUNT(*) FROM ', @universe_db, '.`group`
UNION ALL
SELECT \'type\', COUNT(*) FROM ', @universe_db, '.type
UNION ALL
SELECT \'dogma_attribute\', COUNT(*) FROM ', @universe_db, '.dogma_attribute
UNION ALL
SELECT \'type_attribute\', COUNT(*) FROM ', @universe_db, '.type_attribute
UNION ALL
SELECT \'constellation\', COUNT(*) FROM ', @universe_db, '.constellation
UNION ALL
SELECT \'star\', COUNT(*) FROM ', @universe_db, '.star
UNION ALL
SELECT \'system\', COUNT(*) FROM ', @universe_db, '.system
UNION ALL
SELECT \'corporation\', COUNT(*) FROM ', @universe_db, '.corporation
UNION ALL
SELECT \'planet\', COUNT(*) FROM ', @universe_db, '.planet
UNION ALL
SELECT \'stargate\', COUNT(*) FROM ', @universe_db, '.stargate
UNION ALL
SELECT \'station\', COUNT(*) FROM ', @universe_db, '.station');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Test system search
SELECT 'Testing system search for Jita...' AS status;

SET @sql = CONCAT('SELECT id, name, security FROM ', @universe_db, '.system WHERE name LIKE \'%Jita%\' LIMIT 5');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- NEXT STEPS
-- ============================================================================
-- 1. Visit https://your-pathfinder.com/setup
-- 2. Click "Build Systems Index" to create search indexes
-- 3. Click "Build System Neighbour" to generate adjacency cache
-- 4. Click "Build Wormholes" to populate wormhole static connections
--
-- Dynamic data (player alliances, sovereignty, etc.) will be discovered
-- automatically via ESI API as Pathfinder operates normally.
--
-- This script is idempotent - you can re-run it to update from a newer SDE.
-- ============================================================================
