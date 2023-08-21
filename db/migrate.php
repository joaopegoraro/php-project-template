#!/usr/bin/php
<?php

declare(strict_types=1);

const TABLE_NAME = "db_version";

parse_str(implode('&', array_slice($argv, 2)), $_GET);

function usage(?string $message = null): void
{
    if ($message) {
        echo $message . PHP_EOL . PHP_EOL;
    }

    echo "Utility script to help manage database migrations." . PHP_EOL;
    echo "Syntax: migrate.php [create|status|up|up-to|reset|down|down-to] [args]" . PHP_EOL;
    echo "options:" . PHP_EOL;
    echo " status\t\tShow the status of the migrations" . PHP_EOL;
    echo " up-one\t\tApplies the next pending migration" . PHP_EOL;
    echo " up\t\tApplies all the pending migrations" . PHP_EOL;
    echo " up-to\t\tApplies all the pending migrations up to the migration with the provided ID" . PHP_EOL;
    echo " \t\tEx: migrate.php up-to 1692628446_example" . PHP_EOL;
    echo " reset\t\tRolls back all the applied migrations" . PHP_EOL;
    echo " down\t\tRolls back the last applied migration" . PHP_EOL;
    echo " down-to\tRolls back all the applied migrations back to the migration with the provided ID" . PHP_EOL;
    echo " \t\tEx: migrate.php down-to 1692628446_example" . PHP_EOL;
    echo PHP_EOL;
    echo "OBS: You need to provide the database connection credentials:" . PHP_EOL;
    echo " Ex: migrate.php up HOST=localhost USER=user PASSWORD=password DATABASE=db_name" . PHP_EOL;
    echo PHP_EOL;

    exit(0);
}

function err($err): void
{
    die($err . PHP_EOL);
}

function openDatabaseConnection(): PDO
{
    $errMessage = fn ($type) => "No {$type} provided";

    $dbHost = $_GET["HOST"] ?? err($errMessage('DB_HOST'));
    $dbUser = $_GET["USER"]  ?? err($errMessage('DB_USER'));
    $dbPassword = $_GET["PASSWORD"]  ?? err($errMessage('DB_PWD'));
    $dbName = $_GET["DATABASE"] ??  err($errMessage('DB_NAME'));

    try {
        $pdo = new PDO(
            dsn: "mysql:host={$dbHost};dbname={$dbName}",
            username: $dbUser,
            password: $dbPassword,
        );
    } catch (PDOException $e) {
        err($e);
    }

    try {
        $pdo->query('SELECT 1 FROM ' . TABLE_NAME);
    } catch (PDOException $e) {
        $table = TABLE_NAME;
        $sql = "CREATE table {$table} (
            id INT( 11 ) AUTO_INCREMENT PRIMARY KEY,
            version_id varchar( 250 ) NOT NULL UNIQUE ,
            tstamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);";
        $pdo->exec($sql);
    }

    return $pdo;
}

function getMigrations(): array
{
    $migrationsDir = __DIR__ . '/migrations/';
    if (!file_exists($migrationsDir)) {
        mkdir($migrationsDir, recursive: true);
    }

    $files = scandir($migrationsDir);
    $files = array_slice($files, 2);

    $migrations = [];
    foreach ($files as $fileName) {
        $matches = [];
        preg_match("#\d+_\w+#", $fileName, $matches);
        if (sizeof($matches) >= 1) {
            $migration = $matches[0];
            if ("{$migration}.down.sql" == $fileName) {
                $migrations[$migration]['down'] = true;
            } else if ("{$migration}.up.sql" == $fileName) {
                $migrations[$migration]['up'] = true;
            }
        }
    }

    foreach ($migrations as $migration => $types) {
        if (!$types['up']) {
            err("{$migration} missing a 'up' file");
        }
        if (!$types['down']) {
            err("{$migration} missing a 'down' file");
        }
    }

    if (sizeof($migrations) < 1) {
        err("No migrations found in '" . __DIR__ . "/migrations'");
    }

    return array_keys($migrations);
}

function status(?PDO $openConn = null): void
{
    $migrations = getMigrations();
    $conn = $openConn ?? openDatabaseConnection();

    echo "ID\t\t\tSTATUS" . PHP_EOL;

    $appliedMigrations = [];
    $table = TABLE_NAME;
    $rows = $conn->query("SELECT * FROM {$table} ORDER BY ID");
    while ($row = $rows->fetch()) {
        $migration = $row['version_id'];
        $timestamp = $row['tstamp'];
        $appliedMigrations[$migration] =  $timestamp;
        echo "{$migration}\tapplied at {$timestamp}" . PHP_EOL;
    }

    foreach ($migrations as $migration) {
        if (!key_exists($migration, $appliedMigrations)) {
            echo "{$migration}\tpending" . PHP_EOL;
        }
    }
}

function up(
    bool $byOne = false,
    ?string $to = null,
): void {
    $migrations = getMigrations();
    $conn = openDatabaseConnection();

    $table = TABLE_NAME;
    try {
        if ($byOne) {

            $migration = $migrations[0];

            $lastMigration = $conn->query("SELECT * FROM {$table} ORDER BY ID DESC LIMIT 1")->fetch();
            if ($lastMigration) {
                $versionId = $lastMigration['version_id'];
                $timestamp = $lastMigration['tstamp'];
                $index = array_search($versionId, $migrations);
                if (!key_exists($index + 1, $migrations)) {
                    err("You already are at the last migration ('{$versionId}' - {$timestamp})");
                }
                $migration = $migrations[$index + 1];
            }

            $sql = file_get_contents(__DIR__ . "/migrations/" . $migration . '.up.sql');
            $conn->exec($sql);

            $stmt = $conn->prepare("INSERT INTO {$table}(version_id) VALUES(?)");
            $stmt->bindParam(1, $migration);
            $stmt->execute();

            echo "'{$migration}' applied" . PHP_EOL;
        } else {
            $lastIndex = array_key_last($migrations);
            if ($to) {
                $lastIndex = array_search($to, $migrations);
                if (!$lastIndex) {
                    err("'{$to}' is not a valid migration in " . __DIR__ . "/migrations");
                }
            }

            $count = 0;
            foreach ($migrations as $index => $migration) {
                if ($index > $lastIndex) {
                    break;
                };


                $stmt = $conn->prepare("SELECT * FROM {$table} WHERE version_id = ?");
                $stmt->bindParam(1, $migration);;
                $stmt->execute();
                $appliedMigration = $stmt->fetch();
                if ($appliedMigration) {
                    continue;
                };

                $sql = file_get_contents(__DIR__ . "/migrations/" . $migration . '.up.sql');
                $conn->exec($sql);

                $stmt = $conn->prepare("INSERT INTO {$table}(version_id) VALUES(?)");
                $stmt->bindParam(1, $migration);
                $stmt->execute();

                echo "'{$migration}' applied" . PHP_EOL;
                $count++;
            }

            if ($count < 1) {
                echo "No migrations were applied" . PHP_EOL . PHP_EOL;
                status($conn);
            } else {
                echo PHP_EOL . "{$count} migrations applied" . PHP_EOL;
            }
        }
    } catch (PDOException $e) {
        err($e);
    }
}

function down(
    bool $byOne = false,
    ?string $to = null,
): void {
    $migrations = getMigrations();
    $conn = openDatabaseConnection();

    $table = TABLE_NAME;
    try {
        if ($byOne) {
            $lastMigration = $conn->query("SELECT * FROM {$table} ORDER BY ID DESC LIMIT 1")->fetch();
            if (!$lastMigration) {
                echo "No migrations to roll back" . PHP_EOL . PHP_EOL;
                status($conn);
            }

            $versionId = $lastMigration['version_id'];
            $index = array_search($versionId, $migrations);
            if (!key_exists($index, $migrations)) {
                err("Last applied migration '{$versionId}' has no 'down' file in " . __DIR__ . "/migrations");
            }

            $migration = $migrations[$index];

            $sql = file_get_contents(__DIR__ . "/migrations/" . $migration . '.down.sql');
            $conn->exec($sql);

            $stmt = $conn->prepare("DELETE FROM {$table} WHERE version_id = ?");
            $stmt->bindParam(1, $migration);
            $stmt->execute();

            echo "'{$migration}' rolled back" . PHP_EOL;
        } else {
            $firstIndex = 0;
            if ($to) {
                $firstIndex = array_search($to, $migrations);
                if (!$firstIndex) {
                    err("'{$to}' is not a valid migration in " . __DIR__ . "/migrations");
                }
            }

            $count = 0;
            foreach (array_reverse($migrations, preserve_keys: true) as $index => $migration) {
                if ($index < $firstIndex) {
                    break;
                };

                $stmt = $conn->prepare("SELECT * FROM {$table} WHERE version_id = ?");
                $stmt->bindParam(1, $migration);
                $stmt->execute();
                $appliedMigration = $stmt->fetch();

                if (!$appliedMigration) {
                    continue;
                };

                $sql = file_get_contents(__DIR__ . "/migrations/" . $migration . '.down.sql');
                $conn->exec($sql);

                $stmt = $conn->prepare("DELETE FROM {$table} WHERE version_id = ?");
                $stmt->bindParam(1, $migration);
                $stmt->execute();

                echo "'{$migration}' rolled back" . PHP_EOL;
                $count++;
            }

            if ($count < 1) {
                echo "No migrations were rolled back" . PHP_EOL . PHP_EOL;
                status($conn);
            } else {
                echo PHP_EOL . "{$count} migrations rolled back" . PHP_EOL;
            }
        }
    } catch (PDOException $e) {
        err($e);
    }
}

function create(string $name): void
{
    $migrationsDir = __DIR__ . '/migrations/';
    if (!file_exists($migrationsDir)) {
        mkdir($migrationsDir, recursive: true);
    }

    $time = time();
    $createFile = fn ($type) => $migrationsDir . "{$time}_{$name}.{$type}.sql";

    $up = $createFile('up');
    echo 'Created ' . $up . PHP_EOL;
    fopen($up, "w");

    $down = $createFile('down');
    echo 'Created ' . $down . PHP_EOL;
    fopen($down, "w");
}

$argsSize = sizeof($argv);
if ($argsSize <= 1) {
    usage();
}

switch ($argv[1]) {
    case 'status':
        status();
        break;

    case 'create':
        if ($argsSize < 3) {
            usage("'create' needs a name as an argument");
        }
        $name = $argv[2];
        if (!$name || is_numeric($name)) {
            usage("{$name} is an invalid argument to 'create'");
        }
        create($name);
        break;

    case 'up':
        up();
        break;

    case 'up-one':
        up(byOne: true);
        break;

    case 'up-to':
        if ($argsSize < 3) {
            err("'up-to' expects a valid version id");
        }
        $to = $argv[2];
        up(to: $to);
        break;

    case 'reset':
        down();
        break;

    case 'down':
        down(byOne: true);
        break;

    case 'down-to':
        if ($argsSize < 3) {
            err("'down-to' expects a valid version id");
        }
        $to = $argv[2];
        down(to: $to);
        break;

    default:
        usage("Invalid option");
}
