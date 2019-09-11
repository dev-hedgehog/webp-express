<?php

namespace WebPExpress;

use \WebPExpress\Paths;

class SelfTestHelper
{

    public static function deleteFilesInDir($dir, $filePattern = "*")
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . $filePattern) as $filename) {
            unlink($filename);
        }
    }

    public static function deleteTestImagesInUploadFolder()
    {
        self::deleteFilesInDir(Paths::getAbsDirById('uploads'), "webp-express-test-image-*");
    }

    public static function copyFile($source, $destination)
    {
        $result = [];
        if (@copy($source, $destination)) {
            return [true, $result];
        } else {
            $result[] = 'Failed to copy *' . $source . '* to *' . $destination . '*';
            if (!@file_exists($source)) {
                $result[] = 'The source file was not found';
            } else {
                if (!@file_exists(dirname($destination))) {
                    $result[] = 'The destination folder does not exist!';
                } else {
                    $result[] = 'This is probably a permission issue. Check that your webserver has permission to ' .
                        'write files in the upload directory (*' . dirname($destination) . '*)';
                }
            }
            return [false, $result];
        }
    }

    public static function randomDigitsAndLetters($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function copyTestImageToUploadFolder($imageType = 'jpeg')
    {
        $result = [];
        switch ($imageType) {
            case 'jpeg':
                $fileNameToCopy = 'focus.jpg';
                break;
            case 'png':
                $fileNameToCopy = 'alphatest.png';
                break;
        }
        $testSource = Paths::getPluginDirAbs() . '/webp-express/test/' . $fileNameToCopy;
        $filenameOfDestination = 'webp-express-test-image-' . self::randomDigitsAndLetters(6) . '.' . $imageType;
        $result[] = 'Copying ' . strtoupper($imageType) . ' to upload folder (*' . $filenameOfDestination . '*)';

        $destDir = Paths::getAbsDirById('uploads');
        $destination = $destDir . '/' . $filenameOfDestination;

        list($success, $errors) = self::copyFile($testSource, $destination);
        if (!$success) {
            $result[count($result) - 1] .= '. FAILED';
            $result = array_merge($result, $errors);
            return [$result, false, ''];
        } else {
            $result[count($result) - 1] .= '. ok!';
//            $result[] = 'We now have a file here:';
//            $result[] = '*' . $destination . '*';
        }
        return [$result, true, $filenameOfDestination];
    }

    public static function copyDummyWebPToCacheFolderUpload($destinationFolder, $destinationExtension, $destinationStructure, $destinationFileNameNoExt, $imageType = 'jpeg')
    {
        $result = [];
        $dummyWebP = Paths::getPluginDirAbs() . '/webp-express/test/test.jpg.webp';

        $result[] = 'Copying dummy webp to the cache root for uploads';
        $destDir = Paths::getCacheDirForImageRoot($destinationFolder, $destinationStructure, 'uploads');
        if (!file_exists($destDir)) {
            $result[] = 'The folder did not exist. Creating folder at: ' . $destinationDir;
            if (!mkdir($destDir, 0777, true)) {
                $result[] = 'Failed creating folder!';
                return [$result, false, ''];
            }
        }
        $filenameOfDestination = $destinationFileNameNoExt . ($destinationExtension == 'append' ? '.' . $imageType : '') . '.webp';
        $destination = $destDir . '/' . $filenameOfDestination;

        list($success, $errors) = self::copyFile($dummyWebP, $destination);
        if (!$success) {
            $result[count($result) - 1] .= '. FAILED';
            $result = array_merge($result, $errors);
            return [$result, false, ''];
        } else {
            $result[count($result) - 1] .= '. ok!';
            $result[] = 'We now have a webp file stored here:';
            $result[] = '*' . $destination . '*';
            $result[] = '';
        }
        return [$result, true, $destination];
    }

    public static function remoteGet($requestUrl, $args = [])
    {
        $result = [];
        $return = wp_remote_get($requestUrl, $args);
        if (is_wp_error($return)) {
            $result[] = 'Request URL: ' . $requestUrl;
            $result[] = 'The remote request errored!';
            return [false, $result, [], $return];
        }
        if ($return['response']['code'] != '200') {
            //$result[count($result) - 1] .= '. FAILED';
            $result[] = 'Request URL: ' . $requestUrl;
            $result[] = 'Response: ' . $return['response']['code'] . ' ' . $return['response']['message'];
            return [false, $result, [], $return];
        }
        return [true, $result, $return['headers'], $return];
    }

    public static function hasHeaderContaining($headers, $headerToInspect, $containString)
    {
        if (!isset($headers[$headerToInspect])) {
            return false;
        }

        // If there are multiple headers, check all
        if (gettype($headers[$headerToInspect]) == 'string') {
            $h = [$headers[$headerToInspect]];
        } else {
            $h = $headers[$headerToInspect];
        }
        foreach ($h as $headerValue) {
            if (stripos($headerValue, $containString) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function hasVaryAcceptHeader($headers)
    {
        if (!isset($headers['vary'])) {
            return false;
        }

        // There may be multiple Vary headers. Or they might be combined in one.
        // Both are acceptable, according to https://stackoverflow.com/a/28799169/842756
        if (gettype($headers['vary']) == 'string') {
            $varyHeaders = [$headers['vary']];
        } else {
            $varyHeaders = $headers['vary'];
        }
        foreach ($varyHeaders as $headerValue) {
            $values = explode(',', $headerValue);
            foreach ($values as $value) {
                if (strtolower($value) == 'accept') {
                    return true;
                }
            }
        }
        return false;
    }

    public static function hasCacheControlOrExpiresHeader($headers)
    {
        if (isset($headers['cache-control'])) {
            return true;
        }
        if (isset($headers['expires'])) {
            return true;
        }
        return false;
    }

    public static function printHeaders($headers)
    {
        $result = [];
        $result[] = '### Response headers:';
        foreach ($headers as $headerName => $headerValue) {
            if (gettype($headerValue) == 'array') {
                foreach ($headerValue as $i => $value) {
                    $result[] = '- ' . $headerName . ': ' . $value;
                }
            } else {
                $result[] = '- ' . $headerName . ': ' . $headerValue;
            }
        }
        $result[] = '';
        return $result;
    }

    private static function trueFalseNullString($var)
    {
        if ($var === true) {
            return 'yes';
        }
        if ($var === false) {
            return 'no';
        }
        return 'could not be determined';
    }

    public static function systemInfo()
    {
        $result = [];
        $result[] = '### System info:';
        $result[] = '- PHP version: ' . phpversion();
        $result[] = '- OS: ' . PHP_OS;
        $result[] = '- Server software: ' . $_SERVER["SERVER_SOFTWARE"];
        $result[] = '- Document Root status: ' . Paths::docRootStatusText();
        if (PathHelper::isDocRootAvailable()) {
            $result[] = '- Document Root: ' . $_SERVER['DOCUMENT_ROOT'];
        }
        if (PathHelper::isDocRootAvailableAndResolvable()) {
            if ($_SERVER['DOCUMENT_ROOT'] != realpath($_SERVER['DOCUMENT_ROOT'])) {
                $result[] = '- Document Root (symlinked resolved): ' . realpath($_SERVER['DOCUMENT_ROOT']);
            }
        }

        $result[] = '- Document Root: ' . Paths::docRootStatusText();
        $result[] = '- Apache module "mod_rewrite" enabled?: ' . self::trueFalseNullString(PlatformInfo::gotApacheModule('mod_rewrite'));
        $result[] = '- Apache module "mod_headers" enabled?: ' . self::trueFalseNullString(PlatformInfo::gotApacheModule('mod_headers'));
        return $result;
    }

    public static function wordpressInfo()
    {
        $result = [];
        $result[] = '### Wordpress info:';
        $result[] = '- Version: ' . get_bloginfo('version');
        $result[] = '- Multisite?: ' . self::trueFalseNullString(is_multisite());
        $result[] = '- Is wp-content moved?: ' . self::trueFalseNullString(Paths::isWPContentDirMoved());
        $result[] = '- Is uploads moved out of wp-content?: ' . self::trueFalseNullString(Paths::isUploadDirMovedOutOfWPContentDir());
        $result[] = '- Is plugins moved out of wp-content?: ' . self::trueFalseNullString(Paths::isPluginDirMovedOutOfWpContent());
        return $result;
    }

    public static function configInfo($config)
    {
        $result = [];
        $result[] = '### WebP Express configuration info:';
        $result[] = '- Destination folder: ' . $config['destination-folder'];
        $result[] = '- Destination extension: ' . $config['destination-extension'];
        $result[] = '- Destination structure: ' . $config['destination-structure'];
        //$result[] = 'Image types: ' . ;
        //$result[] = '';
        $result[] = '(To view all configuration, take a look at the config file, which is stored in *' . Paths::getConfigFileName() . '*)';
        return $result;
    }

    public static function htaccessInfo($config)
    {
        $result = [];
        //$result[] = '*.htaccess info:*';
        //$result[] = '- Image roots with WebP Express rules: ' . implode(', ', HTAccess::getRootsWithWebPExpressRulesIn());
        $result[] = '### .htaccess files that WebP Express have placed rules in:';
        $rootIds = HTAccess::getRootsWithWebPExpressRulesIn();
        foreach ($rootIds as $imageRootId) {
            $result[] = '- ' . Paths::getAbsDirById($imageRootId) . '/.htaccess';
        }
        return $result;
    }

    public static function rulesInImageRoot($config, $imageRootId)
    {
        $result = [];
        $result[] = '### WebP rules in the .htaccess placed in *' . $imageRootId . '*:';
        $file = Paths::getAbsDirById($imageRootId) . '/.htaccess';
        if (!HTAccess::haveWeRulesInThisHTAccess($file)) {
            $result[] = '**NONE!**{: .warn}';
        } else {
            $weRules = HTAccess::extractWebPExpressRulesFromHTAccess($file);
            // remove unindented comments
            //$weRules = preg_replace('/^\#\s[^\n\r]*[\n\r]+/ms', '', $weRules);
            $result[] = '```' . $weRules . '```';
        }
        return $result;
    }

    public static function rulesInUpload($config)
    {
        return self::rulesInImageRoot($config, 'uploads');
    }

    public static function allInfo($config)
    {
        $result = [];
        $result = array_merge($result, self::systemInfo());
        $result = array_merge($result, self::wordpressInfo());
        $result = array_merge($result, self::configInfo($config));
        $result = array_merge($result, self::htaccessInfo($config));
        $result = array_merge($result, self::capabilityTests($config));
        $result = array_merge($result, self::rulesInUpload($config));
        $result = array_merge($result, self::rulesInImageRoot($config, 'wp-content'));
        return $result;
    }

    public static function capabilityTests($config)
    {
        $capTests = $config['base-htaccess-on-these-capability-tests'];
        $result = [];
        $result[] = '### Live tests of .htaccess capabilities:';
        /*$result[] = 'Exactly what you can do in a *.htaccess* depends on the server setup. WebP Express ' .
            'makes some live tests to verify if a certain feature in fact works. This is done by creating ' .
            'test files (*.htaccess* files and php files) in a dir inside the content dir and running these. ' .
            'These test results are used when creating the rewrite rules. Here are the results:';*/

//        $result[] = '';
        $result[] = '- mod_rewrite working?: ' . self::trueFalseNullString(CapabilityTest::modRewriteWorking());
        $result[] = '- mod_header working?: ' . self::trueFalseNullString($capTests['modHeaderWorking']);
        /*$result[] = '- pass variable from *.htaccess* to script through header working?: ' .
            self::trueFalseNullString($capTests['passThroughHeaderWorking']);*/
        $result[] = '- passing variables from *.htaccess* to PHP script through environment variable working?: ' . self::trueFalseNullString($capTests['passThroughEnvWorking']);
        return $result;
    }

    public static function diagnoseFailedRewrite($config)
    {
        if (($config['destination-structure'] == 'image-roots') && (!PathHelper::isDocRootAvailableAndResolvable())) {
            $result[] = 'The problem is probably this combination:';
            if (!PathHelper::isDocRootAvailable()) {
                $result[] = '1. Your document root isn`t available';
            } else {
                $result[] = '1. Your document root isn`t resolvable for symlinks (it is probably subject to open_basedir restriction)';
            }
            $result[] = '2. Your document root is symlinked';
            $result[] = '3. The wordpress function that tells the path of the uploads folder returns the symlink resolved path';

            $result[] = 'I cannot check if your document root is in fact symlinked (as document root isnt resolvable). ' .
                'But if it is, there you have it. The line beginning with "RewriteCond %{REQUEST_FILENAME}"" points to your resolved root, ' .
                'but it should point to your symlinked root. WebP Express cannot do that for you because it cannot discover what the symlink is. ' .
                'Try changing the line manually. When it works, you can move the rules outside the WebP Express block so they dont get ' .
                'overwritten. OR you can change your server configuration (document root / open_basedir restrictions)';
        }

        //$result[] = '## Diagnosing';
        if (PlatformInfo::isNginx()) {
            // Nginx
            $result[] = 'Notice that you are on Nginx and the rules that WebP Express stores in the *.htaccess* files probably does not ' .
                'have any effect. ';
            $result[] = 'Please read the "I am on Nginx" section in the FAQ (https://wordpress.org/plugins/webp-express/)';
            $result[] = 'And did you remember to restart the nginx service after updating the configuration?';

            $result[] = 'PS: If you cannot get the redirect to work, you can simply rely on Alter HTML as described in the FAQ.';
            return $result;
        }

        $modRewriteWorking = CapabilityTest::modRewriteWorking();
        if ($modRewriteWorking !== null) {
            $result[] = 'Running a special designed capability test to test if rewriting works with *.htaccess* files';
        }
        if ($modRewriteWorking === true) {
            $result[] = 'Result: Yes, rewriting works.';
            $result[] = 'It seems something is wrong with the *.htaccess* rules then. ';
            $result[] = 'Or perhaps the server has cached the confuration a while. Some servers ' .
                'does that. In that case, simply give it a few minutes and try again.';
        } elseif ($modRewriteWorking === false) {
            $result[] = 'Result: No, rewriting does not seem to work within *.htaccess* rules.';
            if (PlatformInfo::definitelyNotGotModRewrite()) {
                $result[] = 'It actually seems "mod_write" is disabled on your server. ' .
                    '**You must enable mod_rewrite on the server**';
            } elseif (PlatformInfo::definitelyGotApacheModule('mod_rewrite')) {
                $result[] = 'However, "mod_write" *is* enabled on your server. This seems to indicate that ' .
                    '*.htaccess* files has been disabled for configuration on your server. ' .
                    'In that case, you need to copy the WebP Express rules from the *.htaccess* files into your virtual host configuration files. ' .
                    '(WebP Express generates multiple *.htaccess* files. Look in the upload folder, the wp-content folder, etc).';
                $result[] = 'It could however alse simply be that your server simply needs some time. ' .
                    'Some servers caches the *.htaccess* rules for a bit. In that case, simply give it a few minutes and try again.';
            } else {
                $result[] = 'However, this could be due to your server being a bit slow on picking up changes in *.htaccess*.' .
                    'Give it a few minutes and try again.';
            }
        } else {
            // The mod_rewrite test could not conclude anything.
            if (PlatformInfo::definitelyNotGotApacheModule('mod_rewrite')) {
                $result[] = 'It actually seems "mod_write" is disabled on your server. ' .
                    '**You must enable mod_rewrite on the server**';
            } elseif (PlatformInfo::definitelyGotApacheModule('mod_rewrite')) {
                $result[] = '"mod_write" is enabled on your server, so rewriting ought to work. ' .
                    'However, it could be that your server setup has disabled *.htaccess* files for configuration. ' .
                    'In that case, you need to copy the WebP Express rules from the *.htaccess* files into your virtual host configuration files. ' .
                    '(WebP Express generates multiple *.htaccess* files. Look in the upload folder, the wp-content folder, etc). ';
            } else {
                $result[] = 'It seems something is wrong with the *.htaccess* rules. ';
                $result[] = 'Or perhaps the server has cached the confuration a while. Some servers ' .
                    'does that. In that case, simply give it a few minutes and try again.';
            }
        }
        $result[] = 'Note that if you cannot get redirection to work, you can switch to "CDN friendly" mode and ' .
            'rely on the "Alter HTML" functionality to point to the webp images. If you do a bulk conversion ' .
            'and make sure that "Convert upon upload" is activated, you should be all set. Alter HTML even handles ' .
            'inline css (unless you select "picture tag" syntax). It does however not handle images in external css or ' .
            'which is added dynamically with javascript.';

        $result[] = '## Info for manually diagnosing';
        $result = array_merge($result, self::allInfo($config));
        return $result;
    }
}