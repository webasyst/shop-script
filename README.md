# Shop-Script #

Shop-Script is a robust PHP ecommerce platform with best-in-class analytics tools. Powered by Webasyst framework.

https://www.shop-script.com
https://www.webasyst.com

## System Requirements ##

  * Web Server
		* Apache, nginx or IIS

	* PHP 5.6+
		* spl
		* mbstring
		* iconv
		* json
		* gd or ImageMagick

	* MySQL 4.1+

## Installing Webasyst framework ##

Install Webasyst framework via https://github.com/webasyst/webasyst-framework/ or https://www.webasyst.com/platform/.

## Installing Shop-Script ##

1. Once Webasyst framework is installed, copy Shop-Script source files to /PATH_TO_WEBASYST/wa-apps/shop/ folder:

	via GIT:

		cd /PATH_TO_WEBASYST/wa-apps/shop/
		git clone git://github.com/webasyst/shop-script.git ./

	via SVN:

		cd /PATH_TO_WEBASYST/wa-apps/shop/
		svn checkout http://svn.github.com/webasyst/shop-script.git ./

2. Add the following item to the array in wa-config/apps.php file to enable the Shop-Script app—this file lists all installed apps:

		'shop' => true,

3. Open Webasyst backend in a web browser and click the Shop-Script icon in the main menu to start using the app.

## Updating Shop-Script & Webasyst framework ##

Update source files from the repository and log into Webasyst backend in your browser. All new meta updates will be applied automatically.
