Magento2 Component Package Builder
==================================

Builds a ZIP package of a Magento2 component (module, theme, language or library).

Installation
------------

Use Composer:

	composer require mmenozzi/m2cpb
    
Usage
-----

Execute the following command:

	vendor/bin/m2cpb <src_path> <composer_file_path> <destination_zip_path>
	
Where:

* `<src_path>`, is the path to the root directory of the component (the one which contains the registration.php file).
* `<composer_file_path>`, is the path to the original composer.json file used during development. The following properties must be set: name, version, type, license, authors and autoload.
* `<destination_zip_path>`, is the path to the destination directory of the ZIP package file. The file name of the ZIP package is automatically generated using the Composer's package name and version.

License
-------

This library is under the MIT license. See the complete license in the LICENSE file.