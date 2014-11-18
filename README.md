WebIdeConfigManager
===================

Helper script to store JetBrains IDE configurations in multiple directories.

Put your custom configurations under __ConfigHome__

Sample
./ConfigHome/__MyPlayGround__/templates/myplayground-foo.xml

Exit from PhpStorm

run `php ./WebIdeConfigManager.php` to import all configs.
run `php ./WebIdeConfigManager.php -h MyPlayGround` to import just __MyPlayGround__ config.

Start PhpStorm
