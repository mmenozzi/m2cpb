<?php


class Magento2ComponentPackageBuilder
{
    const TEMP_BUILD_DIR_PREFIX = 'm2cpb_';

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Builds a ZIP package of a Magento2 component (module, theme, language or library).
     *
     * @param string $sourcePath
     *  Path to the root directory of the component (the one which contains the registration.php file).
     *
     * @param string $sourceComposerJsonPath
     *  Path to the original composer.json file used during development. The following properties must be set: name,
     *  version, type, license, authors and autoload.
     *
     * @param $destinationZipPath
     *  Path to the destination directory of the ZIP package file. The file name of the ZIP package is automatically
     *  generated using the Composer's package name and version.
     *
     * @return int
     */
    public function build($sourcePath, $sourceComposerJsonPath, $destinationZipPath)
    {
        $this->validateSourcePath($sourcePath);
        $sourcePath = realpath($sourcePath);
        $sourceComposerData = $this->validateSourceCompoerFile($sourceComposerJsonPath);
        $sourceComposerJsonPath = realpath($sourceComposerJsonPath);
        $this->validateDestinationZipPath($destinationZipPath);
        $destinationZipPath = realpath($destinationZipPath);
        $buildDirectory = $this->prepareBuildDirectory($sourcePath);
        $destinationComposerData = $this->getDestinationComposerData($sourceComposerData);
        $this->remapAutoload($sourcePath, $sourceComposerJsonPath, $destinationComposerData);
        $this->deployDestinationComposerFile($buildDirectory, $destinationComposerData);

        $packageName = $destinationComposerData->name;
        $version = $destinationComposerData->version;

        $zipFilePath = $destinationZipPath . DIRECTORY_SEPARATOR . $this->generateZipFilename($packageName, $version);
        $this->zipDir($buildDirectory, $zipFilePath);

        $this->output->writeln(sprintf('Package successfully built in "%s"!', $zipFilePath));

        return 0;
    }

    public function usage()
    {
        return <<<USAGE
Magento2 Component Package Builder
Builds a ZIP package of a Magento2 component (module, theme, language or library).
    
Usage: m2cpb <src_path> <composer_file_path> <destination_zip_path>

    <src_path>              Path to the root directory of the component (the one which contains the registration.php
                            file).
    
    <composer_file_path>    Path to the original composer.json file used during development. The following properties
                            must be set: name, version, type, license, authors and autoload.
                            
    <destination_zip_path>  Path to the destination directory of the ZIP package file. The file name of the ZIP package
                            is automatically generated using the Composer's package name and version.

USAGE;
    }

    private function copyDir($src, $dst)
    {
        if (is_link($src)) {
            symlink(readlink($src), $dst);
        } elseif (is_dir($src)) {
            mkdir($dst);
            foreach (scandir($src) as $file) {
                if ($file != '.' && $file != '..') {
                    $this->copyDir("$src/$file", "$dst/$file");
                }
            }
        } elseif (is_file($src)) {
            copy($src, $dst);
        } else {
            throw new \RuntimeException(sprintf('Cannot copy "%s" (unknown file type).', $src));
        }
    }

    private function zipDir($dir, $zipFile)
    {
        // Get real path for our folder
        $rootPath = realpath($dir);

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();
    }

    /**
     * @param $sourcePath
     */
    private function validateSourcePath($sourcePath)
    {
        if (!is_dir($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException(
                sprintf('Source path "%s" is not a directory or is not readable.', $sourcePath)
            );
        }
        $registrationFile = rtrim($sourcePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'registration.php';
        if (!is_file($registrationFile)) {
            throw new \RuntimeException(
                sprintf('Cannot find Magento2 component registration file at path "%s".', $registrationFile)
            );
        }
    }

    /**
     * @param $sourceComposerJsonPath
     * @return mixed
     */
    private function validateSourceCompoerFile($sourceComposerJsonPath)
    {
        if (is_null($composerData = json_decode(file_get_contents($sourceComposerJsonPath)))) {
            throw new \RuntimeException(
                sprintf('Cannot decode source Composer file at path "%s".', $sourceComposerJsonPath)
            );
        }
        $requiredProperties = array('name', 'version', 'type', 'license', 'authors', 'autoload');
        foreach ($requiredProperties as $requiredProperty) {
            if (!isset($composerData->{$requiredProperty})) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot find required property "%s" in source Composer file at path "%s"',
                        $requiredProperty,
                        $sourceComposerJsonPath
                    )
                );
            }
        }
        return $composerData;
    }

    /**
     * @param $sourcePath
     * @return string
     */
    private function prepareBuildDirectory($sourcePath)
    {
        $buildDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid(self::TEMP_BUILD_DIR_PREFIX);
        $this->copyDir($sourcePath, $buildDirectory);
        return $buildDirectory;
    }

    /**
     * @param $sourceComposerData
     * @return stdClass
     */
    private function getDestinationComposerData($sourceComposerData)
    {
        $destinationComposerData = new \stdClass();
        $destinationComposerData->name = $sourceComposerData->name;
        if (isset($sourceComposerData->description)) {
            $destinationComposerData->description = $sourceComposerData->description;
        }
        $destinationComposerData->version = $sourceComposerData->version;
        $destinationComposerData->type = $sourceComposerData->type;
        $destinationComposerData->license = $sourceComposerData->license;
        $destinationComposerData->authors = $sourceComposerData->authors;
        if (isset($sourceComposerData->require)) {
            $destinationComposerData->require = $sourceComposerData->require;
        }
        $destinationComposerData->autoload = $sourceComposerData->autoload;
        return $destinationComposerData;
    }

    /**
     * @param $sourcePath
     * @param $sourceComposerJsonPath
     * @param $destinationComposerData
     */
    private function remapAutoload($sourcePath, $sourceComposerJsonPath, $destinationComposerData)
    {
        $autoloadRemapPath = trim(str_replace(dirname($sourceComposerJsonPath), '', $sourcePath), DIRECTORY_SEPARATOR);
        if (!empty($autoloadRemapPath)) {
            $autoloadRemapPath = $autoloadRemapPath . DIRECTORY_SEPARATOR;
            foreach ($destinationComposerData->autoload as &$rules) {
                foreach ($rules as &$rulePath) {
                    $rulePath = str_replace($autoloadRemapPath, '', $rulePath);
                }
            }
        }
    }

    /**
     * @param $buildDirectory
     * @param $destinationComposerData
     */
    private function deployDestinationComposerFile($buildDirectory, $destinationComposerData)
    {
        file_put_contents(
            $buildDirectory . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode($destinationComposerData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @param $destinationZipPath
     */
    private function validateDestinationZipPath($destinationZipPath)
    {
        if (!is_dir($destinationZipPath) || !is_writable($destinationZipPath)) {
            throw new \RuntimeException(
                sprintf('Given ZIP destination path "%s" is not a directory or is not writable.', $destinationZipPath)
            );
        }
    }

    /**
     * @param $packageName
     * @param $version
     * @return string
     */
    private function generateZipFilename($packageName, $version)
    {
        return str_replace(DIRECTORY_SEPARATOR, '-', $packageName) . '-' . $version . '.zip';
    }
}
