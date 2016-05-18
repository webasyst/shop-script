# Shop-Script 7 #

Shop-Script 7 is a robust PHP ecommerce framework with best-in-class analytics tools. Powered by Webasyst Framework.

http://www.shop-script.com
http://www.webasyst.com

## System Requirements ##

  * Web Server
		* e.g. Apache or IIS

	* PHP 5.2+
		* spl extension
		* mbstring
		* iconv
		* json
		* gd or ImageMagick extension

	* MySQL 4.1+

## Installing Webasyst Framework ##

Install Webasyst Framework via http://github.com/webasyst/webasyst-framework/ or http://www.webasyst.com/framework/

## Installing Shop-Script ##

1. Once Webasyst Framework is installed, get Shop-Script code into your /PATH_TO_WEBASYST/wa-apps/shop/ folder:

	via GIT:

		cd /PATH_TO_WEBASYST/wa-apps/shop/
		git clone git://github.com/webasyst/shop-script.git ./

	via SVN:

		cd /PATH_TO_WEBASYST/wa-apps/shop/
		svn checkout http://svn.github.com/webasyst/shop-script.git ./

2. Add the following line into the /wa-config/apps.php file (this file lists all installed apps):

		'shop' => true,

3. Done. Run Webasyst backend in a web browser and click on Shop-Script icon in the main app list.

## Updating Webasyst Framework ##

Staying with the latest version of Shop-Script is easy: simply update your files from the repository and login into Webasyst, and all required meta updates will be applied to Webasyst and its apps automatically.
