WebIdeConfigManager
===================

Helper script to store JetBrains IDE configurations in multiple directories.

Put your custom configurations under __ConfigHome__

Sample
./ConfigHome/__MyPlayGround__/templates/myplayground-foo.xml

Troubleshooting
===================

## PhpStorm 7

* If you get the error `PHP Fatal error:  Uncaught exception 'Exception' with message '.WebIde## not found'`, create a symlink from the PhpStorm config to your user's config via: `ln -s ~/Library/Preferences/WebIde70 ~/.WebIde70`
