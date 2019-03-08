<?php

/**
 * Provides Deployer tasks to upload and download files
 * from and to server. When not explicitly using the
 * xyz:files-no-bak, a backup of the current files is
 * created, before new files are transferred.
 *
 * Requires these Deployer variables to be set:
 *   sync_dirs: Array of paths, that will be simultaneously updated
 *              with $absoluteLocalPath => $absoluteRemotePath
 *              If a path has a trailing slash, only its content
 *              will be transferred, not the directory itself.
 *   local_bedrock_root: Absolute path to Bedrock root folder on local host machine
 * 
 * TODO:
 * - after files pull/push execute wp media import ??
 *   
 */

namespace Deployer;

/*
 * Uploads all files (and directories) from local machine to
 * remote server. Overwrites existing files on server with
 * updated local files and uploads new files. Locally deleted
 * files are not deleted on server.
 */
desc('Upload sync directories from local to server');
task('push:files-no-bak', function () use ($getUBPath) {

	foreach (get('sync_dirs') as $localDir => $serverDir) {

		if (get('is_windows_bash')) {
			$localDir = $getUBPath($localDir);
		}
		upload($localDir, $serverDir);
	};

});

/*
 * Downloads all files (and directories) from remote server to
 * local machine. Overwrites existing files on local machine with
 * updated server files and downloads new files. Deleted files
 * on the server are not deleted on local machine.
 */
desc('Download sync directories from server to local');
task('pull:files-no-bak', function () use ($getUBPath) {

	foreach (get('sync_dirs') as $localDir => $serverDir) {

		if (get('is_windows_bash')) {
			$localDir = $getUBPath($localDir);
		}
		download($serverDir, $localDir);
	};

});

desc('Create backup from sync directories on server');
task('backup:remote_files', function () {

	// remote dump folder
	$remoteRoot = get('deploy_path');
	$remoteDump = $remoteRoot . '/' . get('dump_folder');

	foreach (get('sync_dirs') as $localDir => $serverDir) {

		$backupFilename = '_remote_upload_backup_' . date('Y-m-d_H-i-s') . '.zip';

    // Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
		if (substr($serverDir, -1) == '/') {
		} else {
			$serverDir = $serverDir . '/';
		}
		// Add everything from synced directory to zip, but exclude previous backups
		writeln("<comment>Create a backup from sync directories on {$remoteDump}/{$backupFilename}<comment>");
		run("cd {$serverDir} && zip -r {$remoteDump}/{$backupFilename} .");
	};

});

desc('Create backup from sync directories on local machine');
task('backup:local_files', function () use ($getUBPath) {

	$localRoot = get('local_bedrock_root');
	$localDump = $localRoot . '/' . get('dump_folder');

	foreach (get('sync_dirs') as $localDir => $serverDir) {

		$backupFilename = '_local_upload_backup_' . date('Y-m-d_H-i-s') . '.zip';

  	// Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
		if (substr($localDir, -1) == '/') {
		} else {
			$localDir = $localDir . '/';
		}
		// Add everything from synced directory to zip, but exclude previous backups
		writeln("<comment>Create a backup from sync directories on {$localDump}/{$backupFilename}<comment>");
		runLocally("cd {$localDir} && zip -r {$localDump}/{$backupFilename} .");
	};

});

desc('Upload sync directories from local to server after making backup of remote files');
task('push:files', [
	'backup:remote_files',
	'push:files-no-bak',
]);

desc('Download sync directories from server to local machine after making backup of local files');
task('pull:files', [
	'backup:local_files',
	'pull:files-no-bak',
]);
