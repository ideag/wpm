# WordPress Package Manager

A simple tool to setup and maintain your WordPress dependencies.

## Notice

This is very much work in progress, not fully functional and not intended to use in production environment.
Currently only installing a new environment is functional, updating is in the ToDo list.

## Requirements

Git, Subversion, [PHP CLI](http://php.net/features.commandline) and [WordPress CLI](http://wp-cli.org)

## Usage

**Step 1.** Download `wpm.php` and put it in your server.

**Step 2.** Define information about your WordPress install in  `wpm.json` file. What version of the core should be installed, what plugins and themes (and their versions) should be installed, what users shoud be created, etc. (see `wpm-sample.json` for examples). Version declarations have basic support for `*`, `^` and `~` modifiers: `1.0.*`, `~1.0.0` and `^1.0.0`.

**Step 3.** Run `php wpm.php install`. This will create a directory named `wordpress` and `wp-config.php` file in the current directory.

**Step 4.** Point your web server (Apache/nginx/etc.) to the `wordpress` directory as root of your website.

## Theme/Plugin declarations

If theme/plugin is hosted on [WordPress.org](http://wordpress.org), declare them as `org/plugin-name`. For example Akismet should be declared as `org/akismet` and TwentyFifteen - as `org/TwentyFifteen`.

You can also include themes from [WordPress.com](http://wordpress.com), by declaring them as `com/themename` (*Note*: Automattic does not provide version history for these themes, so just declare version as `*`).

If plugin or theme is hosted on github, you can declare them via `user/repo`. For example, `ideag/gust` or `ideag/launch`. WPM expects repository to have tags for versions. The repo has to be public at the moment.

## ToDo

* Custom git/svn source repos for themes/plugins
* Update core/plugins/themes/users

## License & Copyright

WPM was built by [Arūnas Liuiza](http://arunas.co) and is released under GPL2.

## Support

Bug reports, suggestions and pull requests are more than welcome and Github has awesome tools for that.
P.S. I love [coffee](http://arunas.co/#coffee).
