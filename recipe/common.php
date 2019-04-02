<?php

/**
 * Collection of common deployment tasks.
 */

namespace Deployer;

desc('Activate all plugins');
task('activate:plugins', function () {
	$remoteRoot = get('deploy_path');

	// remote Wordpress wp-config.php file path
	$remoteWP = $remoteRoot . '/release/web';
	run("cd {$remoteWP} && wp plugin activate --all");
});

/**
 * Returns Unix-style path from a Windows-style path
 */
$getUBPath = function ($fullPath) {

	// validates a Windows path with drive letter
	if (!preg_match('/^\w:/', $fullPath)) {
		writeln("<comment>{$fullPath} is not a full windows path!</comment>");
		return;
	}

	$unixPath = runLocally("cygpath -u {$fullPath}");

	return $unixPath;
};

/**
 * Create a dump folder to store all backup and dump files from Deployer tasks
 * 
 */
desc('Create dump folder');
task('bedrock:prepare', function () {

	$localBedrock = get('local_bedrock_root');
	$remoteBedrock = get('deploy_path');

	// create dump folder if not exist on local machine
	// TODO
	// tried this 2 approaches but doesn't work!!
	// if (testLocally("[! -d $localBedrock ]")) {
	// 	runLocally("cd $localBedrock && mkdir {{dump_folder}} ");
	// }

	// runLocally("cd $localBedrock && if [ ! -d {{dump_folder}} ]; then mkdir {{dump_folder}}; fi");

	// create dump folder if not exist on remote machine
	run("cd $remoteBedrock && if [ ! -d {{dump_folder}} ]; then mkdir {{dump_folder}}; fi");
});
