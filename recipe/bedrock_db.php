<?php

/**
 * Deployer recipes to push Bedrock database from local development
 * machine to a server and vice versa.
 *
 * Assumes that Bedrock runs locally on a Vagrant machine and uses
 * "vagrant ssh" command to run WP CLI on local server.
 *
 * Will always create a DB backup on the target machine.
 *
 * Requires these Deployer variables to be set:
 *   vagrant_dir: Absolute path to directory that contains .vagrantfile
 *   vagrant_root: Absolute path to website inside Vagrant machine (should mirror local_root)
 *   local_bedrock_root: Absolute path to Bedrock root folder on local host machine
 */

namespace Deployer;

use Dotenv;

/**
 * Returns the local WP URL or false, if not found.
 *
 * @return false|string
 */
$getLocalEnv = function () use ($getUBPath) {
	$localRoot = get('local_bedrock_root');
	$localEnv = Dotenv\Dotenv::create($localRoot, '.env');
	$localEnv->overload();
	$localUrl = getenv('WP_HOME');

	if (!$localUrl) {
		writeln("<error>WP_HOME variable not found in local .env file</error>");

		return false;
	}

	return $localUrl;
};

/**
 * Returns the remote WP URL or false, if not found.
 * Downloads the remote .env file to a local tmp file
 * to extract data.
 *
 * @return false|string
 */
$getRemoteEnv = function () use ($getUBPath) {
	$localRoot = get('local_bedrock_root');
	if (get('is_windows_bash')) {
		$localUBRoot = $getUBPath($localRoot);
	}
	$tmpEnvFile = $localUBRoot . '/.env-remote';
	download(get('deploy_path') . '/release' . '/.env', $tmpEnvFile);
	$remoteEnv = Dotenv\Dotenv::create($localRoot, '.env-remote');
	$remoteEnv->overload();
	$remoteUrl = getenv('WP_HOME');
    // Cleanup tempfile
	runLocally("rm {$tmpEnvFile}");

	if (!$remoteUrl) {
		writeln("<error>WP_HOME variable not found in remote .env file</error>");

		return false;
	}

	return $remoteUrl;
};

/**
 * Removes the protocol and trailing slash from submitted url.
 *
 * @param $url
 * @return string
 */
$urlToDomain = function ($url) {
	return preg_replace('/^https?:\/\/(.+)/i', '$1', rtrim($url, "/"));
};


desc('Pulls DB from server and installs it locally, after having made a backup of local DB');
task('pull:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain, $getUBPath) {

	// local Bedrock folder and remote deploy folder
	$localRoot = get('local_bedrock_root');
	if (get('is_windows_bash')) {
		// a Unix-style path to use in rsync
		$localUBRoot = $getUBPath($localRoot);
	}
	$remoteRoot = get('deploy_path');

	// remote Wordpress wp-config.php file path
	$remoteWP = $remoteRoot . '/release/web';

	// local and remote dump folders
	$localDump = $localRoot . '/' . get('dump_folder');
	$localUBDump = $localUBRoot . '/' . get('dump_folder');
	$remoteDump = $remoteRoot . '/' . get('dump_folder');

	// vagrant folders
	$vagrantDir = get('vagrant_dir');
	$vagrantRoot = get('vagrant_root');

	// Export server db to dump folder
	$exportFilename = '_db_remote_' . date('Y-m-d_H-i-s') . '.sql';
	$exportAbsFile = $remoteDump . '/' . $exportFilename;
	writeln("<comment>Exporting server DB to {$exportAbsFile}</comment>");
	run("cd {$remoteWP} && wp db export {$exportAbsFile}");

  // Download db export
	$downloadedExport = $localUBDump . '/' . $exportFilename;
	writeln("<comment>Downloading DB export to {$downloadedExport}</comment>");
	download($exportAbsFile, $downloadedExport);

  // Cleanup exports on server
	writeln("<comment>Cleaning up {$exportAbsFile} on server</comment>");
	run("rm {$exportAbsFile}");

  // Create backup of local DB
	$backupFilename = '_db_local_' . date('Y-m-d_H-i-s') . '.sql';
	$vagrantDump = $vagrantRoot . '/' . get('dump_folder');
	$backupAbsFile = $vagrantDump . '/' . $backupFilename;
	writeln("<comment>Making backup of DB on local machine to {$backupAbsFile}</comment>");
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp db export {$backupAbsFile}\"");

  // Empty local DB
	writeln("<comment>Reset server DB</comment>");
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp db reset\"");

  // Import export file
	writeln("<comment>Importing {$downloadedExport}</comment>");
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp db import {$vagrantDump}/{$exportFilename}\"");

  // Load local .env file and get local WP URL
	if (!$localUrl = $getLocalEnv()) {
		return;
	}

  // Load remote .env file and get remote WP URL
	if (!$remoteUrl = $getRemoteEnv()) {
		return;
	}

  // Also get domain without protocol and trailing slash
	$localDomain = $urlToDomain($localUrl);
	$remoteDomain = $urlToDomain($remoteUrl);

  // Update URL in DB
  // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
  // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
  // to specify the old (remote) domain as --url parameter.
	writeln("<comment>Updating the URLs in the DB</comment>");
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp search-replace '{$remoteUrl}' '{$localUrl}' --url='{$remoteDomain}' --network\"");
  // Also replace domain (multisite WP also uses domains without protocol in DB)
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp search-replace '{$remoteDomain}' '{$localDomain}' --url='{$remoteDomain}' --network\"");
});

desc('Pushes DB from local machine to server and installs it, after having made a backup of server DB');
task('push:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain, $getUBPath) {

	// local Bedrock folder and remote deploy folder
	$localRoot = get('local_bedrock_root');
	if (get('is_windows_bash')) {
		// a Unix-style path to use in rsync
		$localUBRoot = $getUBPath($localRoot);
	}
	$remoteRoot = get('deploy_path');

	// remote Wordpress wp-config.php file path
	$remoteWP = $remoteRoot . '/release/web';

	// local and remote dump folders
	$localDump = $localRoot . '/' . get('dump_folder');
	$localUBDump = $localUBRoot . '/' . get('dump_folder');
	$remoteDump = $remoteRoot . '/' . get('dump_folder');

	// vagrant folders
	$vagrantDir = get('vagrant_dir');
	$vagrantRoot = get('vagrant_root');

  // Export db on Vagrant server
	$exportFilename = '_db_local_' . date('Y-m-d_H-i-s') . '.sql';
	$vagrantDump = $vagrantRoot . '/' . get('dump_folder');
	$exportAbsFile = $localUBDump . '/' . $exportFilename;
	writeln("<comment>Exporting Vagrant DB to {$exportAbsFile}</comment>");
	runLocally("cd {$vagrantDir} && vagrant ssh -- -t \"cd {$vagrantRoot}/web; wp db export {$vagrantDump}/{$exportFilename}\"");
	
	// Upload export to server
	$uploadedExport = $remoteDump . '/' . $exportFilename;
	writeln("<comment>Uploading export to {$uploadedExport} on server</comment>");
	upload($exportAbsFile, $uploadedExport);

	// Create backup of server DB
	$backupFilename = '_db_remote_' . date('Y-m-d_H-i-s') . '.sql';
	$backupAbsFile = $remoteDump . '/' . $backupFilename;
	writeln("<comment>Making backup of DB on server to {$backupAbsFile}</comment>");
	run("cd {$remoteWP} && wp db export {$backupAbsFile}");

  // Empty server DB
	writeln("<comment>Reset server DB</comment>");
	run("cd {$remoteWP} && wp db reset");

  // Import export file
	writeln("<comment>Importing {$uploadedExport}</comment>");
	run("cd {$remoteWP} && wp db import {$uploadedExport}");

  // Load local .env file and get local WP URL
	if (!$localUrl = $getLocalEnv()) {
		return;
	}

    // Load remote .env file and get remote WP URL
	if (!$remoteUrl = $getRemoteEnv()) {
		return;
	}

  // Also get domain without protocol and trailing slash
	$localDomain = $urlToDomain($localUrl);
	$remoteDomain = $urlToDomain($remoteUrl);

   // Update URL in DB
   // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
   // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
   // to specify the old (local) domain as --url parameter.
	writeln("<comment>Updating the URLs in the DB</comment>");
	run("cd {$remoteWP} && wp search-replace \"{$localUrl}\" \"{$remoteUrl}\" --skip-themes --url='{$localDomain}' --network");
  // Also replace domain (multisite WP also uses domains without protocol in DB)
	run("cd {$remoteWP} && wp search-replace \"{$localDomain}\" \"{$remoteDomain}\" --skip-themes --url='{$localDomain}' --network");

  // Cleanup uploaded file
	writeln("<comment>Cleaning up {$uploadedExport} from server</comment>");
	run("rm {$uploadedExport}");

});
