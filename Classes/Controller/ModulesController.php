<?php

namespace Anexia\Neos\Monitoring\Controller;

use Composer\Semver\VersionParser;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Composer\ComposerUtility;
use Neos\Flow\Package\PackageInterface;
use Neos\Flow\Package\PackageManagerInterface;

class ModulesController extends BaseController
{
    /**
     * @Flow\Inject
     * @var \Neos\Flow\Http\Client\CurlEngine
     */
    protected $client;

    /**
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @param PackageManagerInterface $packageManager
     * @return void
     */
    public function injectPackageManager(PackageManagerInterface $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    /**
     * @return string
     * @throws \Neos\Flow\Mvc\Exception\NoSuchArgumentException
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     * @throws \Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function indexAction()
    {
        $this->validateRequest();

        $modules = $this->getModuleData();
        $runtime = [
            'platform'                    => 'php',
            'platform_version'            => phpversion(),
            'framework'                   => 'neos',
            'framework_installed_version' => $this->getActivePackageVersion('neos/neos') ?? '',
            'framework_newest_version'    => $this->getLatestPackageVersion('neos/neos') ?? '',
        ];

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode([
            'runtime' => $runtime,
            'modules' => $modules
        ]);
    }

    /**
     * @param $packageName
     * @return string|null
     */
    private function getActivePackageVersion($packageName)
    {
        $activePackages = $this->getActivePackages();
        /** @var PackageInterface $package */
        foreach ($activePackages as $packageKey => $package) {
            if ($package->getComposerName() === $packageName && $this->packageManager->isPackageActive($packageKey)) {
                return $this->getInstalledComposerPackageVersion($package);
            }
        }
        return null;
    }

    /**
     * @param PackageInterface $package
     * @return mixed
     */
    private function getInstalledComposerPackageVersion($package)
    {
        $composerName = $package->getComposerName();
        foreach (ComposerUtility::readComposerLock() as $composerLockData) {
            if (!isset($composerLockData['name'])) {
                continue;
            }
            if ($composerLockData['name'] === $composerName) {
                return $composerLockData['version'];
            }
        }
        return $package->getComposerManifest('version');
    }

    /**
     * @return array
     */
    private function getActivePackages(): array
    {
        $activePackages = [];
        /** @var PackageInterface $package */
        foreach ($this->packageManager->getAvailablePackages() as $packageKey => $package) {
            if ($this->packageManager->isPackageActive($packageKey)) {
                $activePackages[$packageKey] = $package;
            }
        }
        return $activePackages;
    }

    /**
     * @return array
     */
    private function getModuleData(): array
    {
        $activePackages = $this->getActivePackages();

        $modules = [];
        /** @var PackageInterface $package */
        foreach ($activePackages as $package) {
            $module = [
                'name'                       => $package->getPackageKey() ?? '',
                'installed_version'          => $this->getInstalledComposerPackageVersion($package) ?? '',
                'installed_version_licences' => $this->getValueAsArray(
                    $package->getComposerManifest('license') ?? null
                ),
                'newest_version'             => '',
                'newest_version_licences'    => [],
            ];

            $latestStable = $this->getLatestPackage($package->getComposerName());
            if ($latestStable !== null) {
                $module['newest_version'] = $latestStable->version ?? '';
                $module['newest_version_licences'] = $this->getValueAsArray($latestStable->license);
            }

            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * @param mixed $value
     * @return array
     */
    private function getValueAsArray($value): array
    {
        if (empty($value)) {
            return [];
        }
        return \is_array($value) ? $value : [$value];
    }

    /**
     * Return whichever object has the newer version
     *
     * @param object $versionData
     * @param object $lastVersion
     * @return object
     */
    private function getNewerVersion($versionData, $lastVersion)
    {
        $versionNo = $versionData->version;
        $normVersionNo = $versionData->version_normalized;
        $stability = VersionParser::normalizeStability(VersionParser::parseStability($versionNo));
        $isStable = $stability === 'stable';

        if ($lastVersion === null && $isStable) {
            return $versionData;
        }

        // only use stable version numbers
        if ($isStable && version_compare($normVersionNo, $lastVersion->version_normalized) >= 0) {
            return $versionData;
        }

        return $lastVersion;
    }

    /**
     * Get latest (stable) package from packagist
     *
     * @param string $packageName , the name of the package as registered on packagist, e.g. 'laravel/framework'
     * @return object|null
     */
    private function getLatestPackage($packageName)
    {
        // get version information from packagist
        $packagistUrl = 'https://packagist.org/packages/' . $packageName . '.json';
        $latestVersion = null;

        try {
            $uri = new \Neos\Flow\Http\Uri($packagistUrl);
            $request = \Neos\Flow\Http\Request::create($uri, 'GET');
            $response = $this->client->sendRequest($request);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $packagistInfo = json_decode($response->getContent());
            $versions = $packagistInfo->package->versions;
            foreach ($versions as $index => $version) {
                $latestVersion = $this->getNewerVersion($version, $latestVersion);
            }
            return $latestVersion;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $packageName
     * @return string|null
     */
    private function getLatestPackageVersion($packageName)
    {
        $lastVersion = $this->getLatestPackage($packageName);
        if ($lastVersion !== null) {
            return $lastVersion->version;
        }
        return null;
    }
}
