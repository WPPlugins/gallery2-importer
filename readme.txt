=== Gallery2 Importer ===
Contributors: sillybean
Tags: gallery, images, import, gallery2
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 0.3.1
Donate Link: http://sillybean.net/code/wordpress/gallery2-importer/

This plugin allows you to import albums, images, and comments from a Gallery2 installation into your WordPress site. Albums will be saved as pages containing the [gallery] shortcode, and images will be saved as attachments.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` 
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Tools &rarr; Import and choose Gallery2 from the list.
1. Fill in your Gallery2 database settings and import preferences.

NOTE: You should <a href="http://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP">increase your PHP memory limit</a> if the importer stops when it reaches a large image file. In testing, it used up to 82MB on some JPGs. It also takes a fair amount of time -- 20 minutes for a test gallery containing about 900 photos.

== Translations ==

If you would like to send me a translation, please write to me through <a href="http://sillybean.net/about/contact/">my contact page</a>. Let me know which plugin you've translated and how you would like to be credited. I will write you back so you can attach the files in your reply.

== Screenshots ==

1. Some photos in Gallery2.
1. The import settings screen.
1. The import process.
1. The same photos in a WordPress page's gallery.

== Changelog ==

= 0.3.1 =
* Left out an important line, without which new authors would not be created in WordPress 3.0 and earlier.
= 0.3 =
* Now importing comments.
= 0.2 =
* Now preserving authors.
= 0.1 =
* First try, with images and albums only.