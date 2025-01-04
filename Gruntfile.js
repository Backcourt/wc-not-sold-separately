/**
 * Build automation scripts.
 */

 module.exports = function(grunt) {

	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig(
		{
			pkg: grunt.file.readJSON( 'package.json' ),

			// Setting folder templates.
			dirs: {
				fonts: 'assets/fonts',
				js: 'assets/js',
				php: 'includes'
			},

			// # Build and release

			// Remove any files in zip destination and build folder.
			clean: {
				main: ['build/**']
			},

			// Copy the plugin into the build directory.
			copy: {
				main: {
					src: [
						'**',
						'!node_modules/**',
						'!build/**',
						'!deploy/**',
						'!svn/**',
						'!**/*.zip',
						'!**/*.bak',
						'!wp-assets/**',
						'!package-lock.json',
						'!nyp-logo.png',
						'!screenshots/**',
						'!.git/**',
						'!**.md',
						'!Gruntfile.js',
						'!package.json',
						'!gitcreds.json',
						'!.gitcreds',
						'!.gitignore',
						'!.gitmodules',
						'!.code-workspace',
						'!sftp-config.json',
						'!**.sublime-workspace',
						'!**.code-workspace',
						'!**.sublime-project',
						'!deploy.sh',
						'!**/*~',
						'!phpcs.xml',
						'!composer.json',
						'!composer.lock',
						'!vendor/**',
						'!none',
						'!.nvmrc',
						'!.jshintrc',
						'!.distignore',
						'!**/*.scss',
						'!assets//scss/**',
						'!bin/**',
					],
					dest: 'build/'
				}
			},

			// Make a zipfile.
			compress: {
				main: {
					options: {
						mode: 'zip',
						archive: 'deploy/<%= pkg.name %>-<%= pkg.version %>.zip',
					},
					expand: true,
					cwd: 'build/',
					dest: '<%= pkg.name %>',
					src: [ '**/*' ]
				},
			},

			// # Internationalization

			// Add text domain.
			addtextdomain: {
				options: {
					textdomain: '<%= pkg.name %>',    // Project text domain.
					updateDomains: [ 'woocommerce-mix-and-match', 'woocommerce-product-bundles', 'woocommerce-mix-and-match-min-max-quantities', 'woocommerce' ]  // List of text domains to replace.
				},
				target: {
					files: {
						src: ['*.php', '**/*.php', '**/**/*.php', '!node_modules/**', '!deploy/**']
					}
				}
			},

			// Generate .pot file.
			makepot: {
				target: {
					options: {
						domainPath: '/languages', // Where to save the POT file.
						exclude: ['deploy','build','node_modules'], // List of files or directories to ignore.
						mainFile: '<%= pkg.name %>.php', // Main project file.
						potFilename: '<%= pkg.name %>.pot', // Name of the POT file.
						type: 'wp-plugin', // Type of project (wp-plugin or wp-theme).
						potHeaders: {
							'Report-Msgid-Bugs-To': 'https://woocommerce.com/my-account/tickets/'
						}
					}
				}
			},

			// Bump version numbers (replace with version in package.json).
			replace: {
				version: {
					src: [
					'readme.txt',
					'<%= pkg.name %>.php',
					],
					overwrite: true,
					replacements: [
					{
						from: /Stable tag:.*$/m,
						to: "Stable tag: <%= pkg.version %>"
					},
					{
						from: /Version:.*$/m,
						to: "Version: <%= pkg.version %>"
					},
					{
						from: /public \$version = \'.*.'/m,
						to: "public $version = '<%= pkg.version %>'"
					},
					{
						from: /public \$version = \'.*.'/m,
						to: "public $version = '<%= pkg.version %>'"
					},
					{
						from: /const VERSION = \'.*.'/m,
						to: "const VERSION = '<%= pkg.version %>'"
					}
					]
				},
				prerelease: {
					src: [
					'readme.txt',
					'<%= pkg.name %>.php',
					],
					overwrite: true,
					replacements: [{
						from: /Stable tag:.*$/m,
						to: "Stable tag: <%= pkg.version %>"
					}, {
						from: /Version:.*$/m,
						to: "Version: <%= pkg.version %>"
					}, {
						from: /public \$version = \'.*.'/m,
						to: "public $version = '<%= pkg.version %>'"
					}, {
						from: /public \$version      = \'.*.'/m,
						to: "public $version      = '<%= pkg.version %>'"
					}]
				}
			}

		}
	);

	// Register tasks.
	grunt.registerTask(
		'default',
		[
		'replace',
		]
	);

	grunt.registerTask(
		'zip',
		[
		'clean',
		'copy',
		'compress'
		]
	);

	grunt.registerTask( 'dev', [ 'replace:prerelease' ] );
	grunt.registerTask( 'build', [ 'dev', 'addtextdomain', 'makepot' ] );
	grunt.registerTask( 'prerelease', [ 'build', 'zip', 'clean' ] );
	grunt.registerTask( 'release', [ 'replace:version', 'addtextdomain', 'makepot', 'build', 'zip', 'clean' ] );

};
