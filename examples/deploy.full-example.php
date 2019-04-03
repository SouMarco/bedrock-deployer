<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/soumarco/bedrock-deployer/recipe/common.php';
require 'vendor/soumarco/bedrock-deployer/recipe/bedrock_db.php';
require 'vendor/soumarco/bedrock-deployer/recipe/bedrock_env.php';
require 'vendor/soumarco/bedrock-deployer/recipe/bedrock_misc.php';
require 'vendor/soumarco/bedrock-deployer/recipe/filetransfer.php';


// ********** TODO:
// - create dump folder if not exists in local machine
// - create a rollback task with database rollback


// Configuration

set('is_windows_bash', true); // Using windows bash on local host machine?
set('dump_folder', 'dump'); // A folder name, located on Bedrock root folder, to store backup and dump files created by tasks

// Common Deployer config
set('repository', 'webdevdi@vs-ssh.visualstudio.com:v3/webdevdi/EA-EPE/EA-EPE-Bed');
set('shared_dirs', [
	'web/app/uploads'
]);

// File transfer config
set('sync_dirs', [
	dirname(__FILE__) . '/web/app/uploads/' => '{{deploy_path}}/shared/web/app/uploads/',
]);

// Environment config
set('vagrant_dir', dirname(__FILE__)); // Absolute path to Vagrant host folder that contains .vagrantfile
set('vagrant_root', '/vagrant'); // Absolute path to Vagrant guest machine web server folder

// Bedrock root folder
set('local_bedrock_root', dirname(__FILE__)); // Absolute path to Bedrock root folder on local host machine

// Miscellaneous
set('keep_release', 10);

// Hosts

inventory('hosts.yml');

host('sitedev')
	->stage('test')
	->user('mamaral')
	->set('deploy_path', '/var/www/html');

host('ea-epe-demo')
	->stage('staging')
	->user('mamaral')
	->set('deploy_path', '/var/www/wordpress');

host('ea-epe-19')
	->stage('production')
	->user('mamaral')
	->set('deploy_path', '/var/www/wordpress');

// Tasks

// IMPORTANT:
// before initial deploy, check if:
//  - local machine has vagrant machine up
//  - local machine has ssh config properly setting
//  - remote machine has ssh keys (private key to Git repositories access and public key to local machine access)
//  - copy local to remote machine auth.json file (Composer to Github token)
// 	- remote machine has database created
//  - remote machine has WP-CLI
//  - local machine has dump folder (see TODO)
//  - to run correctly deploy:writable, remote machine has a mamaral sudoers config file:
// /etc/sudoers.d/mamaral with file permissions 440
// Cmnd_Alias CHOWNWEB1 = /bin/chown -RL www-data web/ ../shared/web/app/uploads/
// Cmnd_Alias CHOWNWEB2 = /bin/chown -RL mamaral web/ ../shared/web/app/uploads/
// mamaral ALL=(root) NOPASSWD: CHOWNWEB1
// mamaral ALL=(root) NOPASSWD: CHOWNWEB2

// Sets webserver user to some folders
set('writable_dirs', ['web/', '../shared/web/app/uploads/']);
set('writable_mode', 'chown');
set('writable_use_sudo', true);

// Tasks to set http_user
desc('Set http_user as www-data');
task('bedrock:www_data', function () {
	set('http_user', 'www-data');
});

desc('Set http_user as mamaral');
task('bedrock:mamaral', function () {
	set('http_user', 'mamaral');
});


// Deployment flow
desc('Deploy project');
task('deploy', [
	'deploy:prepare',
	'bedrock:prepare',
	'deploy:lock',
	'deploy:release',
	'deploy:update_code',
	'deploy:shared',
	'bedrock:vendors',
	'bedrock:env',
	'bedrock:mamaral',
	'deploy:writable',
	'push:files',
	'bedrock:www_data',
	'deploy:writable',
	'push:db',
	'deploy:clear_paths',
	'deploy:symlink',
	'deploy:unlock',
	'cleanup',
	'success',
]);

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Run a rollback task (DO NOT rollback database)
desc('Bedrock rollback');
task('bedrock:rollback', [
	'bedrock:mamaral',
	'deploy:writable',
	'rollback'
]);


// *********** run tasks *********************

// run tasks on local machine command line to deploy
// proj_folder$ vendor/bin/dep deploy <stage>

// run roolback task on local machine
// proj_folder$ vendor/bin/dep bedrock:rollback <stage>
