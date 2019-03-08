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
 * TODO: follow 'test and testLocally' issue to make this task work correctelly
 */
desc('Create dump folder');
task('bedrock:prepare', function () {

	$localBedrock = get('local_bedrock_root');
	$remoteBedrock = get('deploy_path');

	// there are a dump folder?
	// BUG :: Deployer thus not run correctelly 'test' and 'testLocally'
	// see this issue: https://github.com/deployphp/deployer/issues/1577 
	// if (testLocally('[-d ' . $localBedrock . '/dump]')) {
	// 	writeln("<comment>Local machine already has a dump folder!</comment>");
	// 	return;
	// }
	// if (test('[-d ' . $remoteBedrock . '/dump]')) {
	// 	writeln("<comment>Remote machine already has a dump folder!</comment>");
	// 	return;
	// }

	runlocally("cd {$localBedrock} && mkdir {{dump_folder}}");
	run("cd {$remoteBedrock} && mkdir {{dump_folder}}");

});