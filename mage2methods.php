<?php
require 'vendor/autoload.php';

/**
 * @param SplFileInfo $file
 * @param string $classPrefix
 * @param bool $vendor
 * @return string
 */
function getRealClassname(SplFileInfo $file, $classPrefix, $vendor = false)
{
    $path = $file->getRelativePathname();
    if (substr($path, -4) !== '.php') {
        throw new UnexpectedValueException(
            sprintf('Expected that relative file %s ends with ".php"', var_export($path, true))
        );
    }
    $path = substr($path, 0, -4);
    $path = strtr($path, '\\', '/');
    $pathInfo = strtr($path, '/', '\\');
    if ($vendor) {
        $pathInfo = explode('/', $pathInfo);
        $moduleName = str_replace('module-', '', $pathInfo[0]);
        $moduleName = ucwords($moduleName, "-");
        $moduleName = strtr($moduleName, '-', '');
        $pathInfo[0] = $moduleName;
        $pathInfo = implode('/', $pathInfo);
    }
    return trim($classPrefix . '\\' . $pathInfo, '\\');
}

$searchFolder = 'app' . DIRECTORY_SEPARATOR . 'code' . DIRECTORY_SEPARATOR . 'Experius';
$vendor = false;
$classPrefix = 'Experius';

$searchFolder = 'vendor' . DIRECTORY_SEPARATOR . 'magento';
$vendor = true;
$classPrefix = 'Magento';

$finder = Symfony\Component\Finder\Finder::create();
$finder
    ->files()
    ->in([$searchFolder])
    ->followLinks()
    ->ignoreUnreadableDirs(true)
    ->name('*.php')
    ->notName('InstallData*')
    ->notName('InstallSchema*')
    ->notName('UpgradeData*')
    ->notName('registration.php')
    ->notName('UpgradeSchema*');
$fp = fopen('mage2methods.csv', 'w');
fputcsv($fp, ['full_classname', 'method', 'parameters']);
foreach ($finder as $file) {
    if (strpos($file, 'module') === false) {
        continue;
    }
    $classNameByPath = getRealClassname($file, $classPrefix, $vendor);
    $methods = get_class_methods($classNameByPath);
    if ($methods) {
        foreach ($methods as $method) {
            $method = new \ReflectionMethod($classNameByPath, $method);
            if ($method->isPublic() && $method->getName() != '__construct') {
                $result = [
                    $classNameByPath,
                    $method->getName()
                ];
                if ($method->getNumberOfParameters() > 0) {
                    $parameters = [];
                    foreach ($method->getParameters() as $parameter) {
                        $parameters[$parameter->getName()] = '"' . $parameter->getName() . '":""';
                        if ($parameter->isDefaultValueAvailable()) {
                            $default = $parameter->getDefaultValue();
                            switch (gettype($default)) {
                                case 'NULL':
                                    $value = 'null';
                                    break;
                                case 'boolean':
                                    $value = $default ? 'true' : 'false';
                                    break;
                                case 'array':
                                    $value = count($default) ? '['. implode(",", $default) . ']' : '[]';
                                    break;
                                default:
                                    $value = str_replace('"', '\"', var_export($default, true));
                                    break;
                            }
                            $parameters[$parameter->getName()] = '"' . $parameter->getName() . '":"' . $value . '"';
                        }
                    }

                    $jsonString = '{' . implode(',', $parameters) . '}';

                    $result[] = $jsonString;
                }
                fputcsv($fp, $result, ';', "'");
            }
        }
    }
}
fclose($fp);