<?php

include __DIR__ . "/vendor/autoload.php";

use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Dropbox;

$config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);

if(!isset($argv[1]) && !isset($argv[2])) {
    echo "Usage: php backup.php appName dbName [folderToBackup]\n";
    exit();
}

$accessToken = $config['access_token'] ?? "";
$cleanDays = $config['cleanup_days'] ?? 30;
$dbUser = $config['db_user'] ?? "";
$dbPassword = $config['db_password'] ?? "";

$appName = $argv[1];
$dbName = $argv[2];
$folderToBackup = $argv[3] ?? null;
$backupFolderName = date("d-m-yy");
$backupFileName = $appName . "_" .date("H_i_d-m-yy");

echo "Start backup\n-------------------\n";
echo "App: $appName\n";
echo "Database: $dbName\n";
if ($folderToBackup) {
    echo "Folder to add to backup: $folderToBackup\n";
}
echo "-------------------\n";


$app = new DropboxApp("", "", $accessToken);

$dropbox = new Dropbox($app);

exec("mkdir $backupFileName");
exec("mysqldump -u $dbUser -p$dbPassword $dbName > $backupFileName/$dbName.sql");

if ($folderToBackup) {
    exec("cp -r $folderToBackup $backupFileName");
}

exec("tar -zcvf $backupFileName.tar.gz $backupFileName");

exec("rm -Rf $backupFileName");

$dropbox->upload("./$backupFileName.tar.gz", "/$backupFolderName/$backupFileName.tar.gz");

exec("rm -Rf $backupFileName.tar.gz");


echo "Looking for old backups to clean up\n";
$listFolderContents = $dropbox->listFolder("/");
$files = $listFolderContents->getItems();


/**
 * @var Kunnu\Dropbox\Models\FileMetadata $file
 */
foreach ($files as $file) {
    if ($file->getData()['.tag'] == "folder") {
        try{
            $time = new DateTime($file->getName());
            $diff = $time->diff(new DateTime("now"));
            if($diff->days > $cleanDays) {
                echo sprintf("Deleting old backup: %s \n", $file->getPathDisplay());
                $dropbox->delete($file->getPathDisplay());
            }
        } catch (\Exception $e) {
            echo "Folder has not a date format\n";
        }


    }
}


echo "Backup done\n";




