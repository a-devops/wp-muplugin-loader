<?php

namespace LkWdwrd\MuPluginLoader\Tests\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installers\Installer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Package\RootPackageInterface;
use Composer\Script\Event;
use LkWdwrd\MuPluginLoader\Composer\MuLoaderPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MuLoaderPluginTest extends TestCase
{
    private const TMP_DIR = PROJECT_TESTS . 'tmp';
    private const TOOLS_DIR = PROJECT_TESTS . 'tools';

    public function tearDown(): void
    {
        parent::tearDown();

        // Delete anything in self::TMP_DIR
        array_map('unlink', glob(self::TMP_DIR . '/mu-plugins/*'));
    }

    public function testGetMuPath()
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ]
            ]
        );

        $this->assertEquals(self::TMP_DIR, $composer->getConfig()->get('vendor-dir'));

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $muPath = $plugin->getMuPath($composer);

        self::assertEquals(self::TMP_DIR . '/mu-plugins/', $muPath);
    }

    public function testDumpRequireFileDumpsExpectedFile(): void
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ]
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileExists(self::TMP_DIR . '/mu-plugins/mu-require.php');
        self::assertFileEquals(self::TOOLS_DIR . '/mu-plugins/mu-require.php', self::TMP_DIR . '/mu-plugins/mu-require.php');
    }

    public function testDumpRequireFileDumpsExpectedFileWithSetFile(): void
    {
        $muRequireFile = 'zzz-mu-require.php';
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ],
                'mu-require-file' => $muRequireFile,
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileExists(self::TMP_DIR . '/mu-plugins/' . $muRequireFile);
        self::assertFileEquals(self::TOOLS_DIR . '/mu-plugins/mu-require.php', self::TMP_DIR . '/mu-plugins/' . $muRequireFile);
    }

    public function testDumpRequireFileDoesNotDumpIfMuRequireFileSetToFalse(): void
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ],
                'mu-require-file' => false,
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileDoesNotExist(self::TMP_DIR . '/mu-plugins/mu-require.php');
    }

    public function testOverridePluginTypesSetsTypeOfPackageOnInstall(): void
    {
        $pluginName = 'vendor/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [
                    $pluginName
                ],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $package = $this->mock_package($pluginName);
        $package->expects(self::once())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(InstallOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testOverridePluginTypesSetsTypeOfPackageOnUpdate(): void
    {
        $pluginName = 'vendor/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [
                    $pluginName
                ],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $package = $this->mock_package($pluginName);
        $package->expects(self::once())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(UpdateOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getTargetPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testOverridePluginTypesSetsTypeOfPackageOnInstallForWpackagistPlugin(): void
    {
        $pluginName = 'wpackagist-plugin/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [
                    'wp-plugin' // Don't include wpackagist-plugin here.
                ],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $package = $this->mock_package($pluginName);
        $package->expects(self::once())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(InstallOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testOverridePluginTypesDoesNotSetTypeIfForceMuIsEmpty(): void
    {
        $pluginName = 'vendor/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $package = $this->mock_package($pluginName);
        $package->expects(self::never())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(UpdateOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getTargetPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testOverridePluginTypesDoesNotSetTypeIfTypeIsNotWordpressPlugin(): void
    {
        $pluginName = 'vendor/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $package = $this->mock_package($pluginName, 'library');
        $package->expects(self::never())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(UpdateOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getTargetPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testFileIsNotDumpedAfterDeactivation(): void
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ]
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileDoesNotExist(self::TMP_DIR . '/mu-plugins/mu-require.php');
    }

    public function testOverridePluginTypesDoesNothingAfterDeactivation(): void
    {
        $pluginName = 'vendor/wp-plugin';

        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        'type:wordpress-muplugin'
                    ]
                ],
                'force-mu' => [
                    $pluginName
                ],
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);

        $package = $this->mock_package($pluginName);
        $package->expects(self::never())->method('setType')->with('wordpress-muplugin');

        $operation = $this->getMockBuilder(UpdateOperation::class)->disableOriginalConstructor()->getMock();
        $operation->method('getTargetPackage')->willReturn($package);

        $packageEvent = $this->getMockBuilder(PackageEvent::class)->disableOriginalConstructor()->getMock();
        $packageEvent->method('getOperation')->willReturn($operation);

        $plugin->overridePluginTypes($packageEvent);
    }

    public function testUninstallRemovesDumpedRequireFile(): void
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ]
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileExists(self::TMP_DIR . '/mu-plugins/mu-require.php');

        $plugin->uninstall($composer, $io);

        self::assertFileDoesNotExist(self::TMP_DIR . '/mu-plugins/mu-require.php');
    }

    public function testDumpRequireFileOverwritesExistingFile(): void
    {
        $composer = $this->mock_composer(
            [
                'installer-paths' => [
                    '/mu-plugins/{$name}' => [
                        "type:wordpress-muplugin"
                    ]
                ]
            ]
        );

        $io = $this->getMockBuilder(IOInterface::class)->getMock();
        $event = $this->mock_event_for_dump_require_file($composer);

        $plugin = new MuLoaderPlugin();
        $plugin->activate($composer, $io);

        $plugin->dumpRequireFile($event);

        self::assertFileExists(self::TMP_DIR . '/mu-plugins/mu-require.php');

        $createdFileModifiedTime = filemtime(self::TMP_DIR . '/mu-plugins/mu-require.php');

        // Sleep for 1 second to ensure the filemtime changes.
        sleep(1);

        $plugin->dumpRequireFile($event);

        $updatedFileModifiedTime = filemtime(self::TMP_DIR . '/mu-plugins/mu-require.php');

        self::assertNotEquals($createdFileModifiedTime, $updatedFileModifiedTime);
    }

    /**
     * @param array $extraConfig Config for the extra section you want returned from getExtra()
     *
     * @return Composer|MockObject
     */
    private function mock_composer(array $extraConfig = [])
    {
        $package = $this->getMockBuilder(RootPackageInterface::class)->getMock();
        $config = $this->getMockBuilder(Config::class)->getMock();
        $installationManager = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $composer = $this->getMockBuilder(Composer::class)->getMock();

        $package->method('getExtra')->willReturn($extraConfig);

        $config->method('has')->with('vendor-dir')->willReturn(false);
        $config->method('get')->with('vendor-dir')->willReturn(self::TMP_DIR);

        $composer->method('getPackage')->willReturn($package);
        $composer->method('getConfig')->willReturn($config);

        $binaryInstaller = $this->getMockBuilder(BinaryInstaller::class)->disableOriginalConstructor()->getMock();

        $installer = new Installer(new NullIO(), $composer, 'library', null, $binaryInstaller);
        $installationManager->method('getInstaller')->with('wordpress-muplugin')->willReturn($installer);

        $composer->method('getInstallationManager')->willReturn($installationManager);

        return $composer;
    }

    /**
     * @param Composer $composer
     * @return Event|MockObject
     */
    private function mock_event_for_dump_require_file(Composer $composer)
    {
        $event = $this->getMockBuilder(Event::class)->disableOriginalConstructor()->getMock();
        $event->method('getComposer')->willReturn($composer);

        return $event;
    }

    /**
     * @param string $pluginName Name of plugin name to return for getName call
     * @param string $pluginType Name of plugin type to return for getType call
     *
     * @return Package|MockObject
     */
    private function mock_package(string $pluginName, string $pluginType = 'wordpress-plugin')
    {
        $package = $this->getMockBuilder(Package::class)->disableOriginalConstructor()->getMock();
        $package->method('getType')->willReturn($pluginType);
        $package->method('getName')->willReturn($pluginName);

        return $package;
    }
}
