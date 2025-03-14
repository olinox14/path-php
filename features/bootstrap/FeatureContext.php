<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterSuite;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;
use Path\Path;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    private string $pathValue = '';
    private Path|null $path = null;
    private string $workingDir;
    private string $tempDir;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    #[BeforeScenario]
    public function prepareScenario(): void
    {
        $this->pathValue = '';
        $this->path = null;
    }

    #[BeforeScenario]
    public function prepareTestFolders(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'path-php' . DIRECTORY_SEPARATOR .
            md5(microtime() . rand(0, 10000));

        mkdir($dir, 0777, true);

        $this->workingDir = $dir;
        $this->tempDir = $dir;
    }

    #[BeforeSuite]
    #[AfterSuite]
    public static function cleanTestFolders(): void
    {
        if (is_dir($dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'path-php')) {
            self::clearDirectory($dir);
        }
    }

    /**
     * Creates a file with specified name and context in current workdir.
     *
     * @param string       $filename name of the file (relative path)
     * @param string       $content
     */
    #[Given('/^(?:there is )?a file named "([^"]*)" with:$/')]
    public function aFileNamedWith(string $filename, string $content): void
    {
        $content = strtr((string) $content, ["'''" => '"""']);
        $this->createFile($this->workingDir . '/' . $filename, $content);
    }

    /**
     * Creates a empty file with specified name in current workdir.
     *
     * @Given /^(?:there is )?a file named "([^"]*)"$/
     *
     * @param string $filename name of the file (relative path)
     */
    public function aFileNamed(string $filename): void
    {
        $this->createFile($this->workingDir . '/' . $filename, '');
    }

    /**
     * Moves user to the specified path.
     *
     * @param string $path
     */
    #[Given('/^I am in the "([^"]*)" path$/')]
    public function iAmInThePath(string $path): void
    {
        $this->moveToNewPath($path);
    }

    /**
     * Checks whether a file at provided path exists.
     *
     * @param   string $path
     */
    #[Given('/^file "([^"]*)" should exist$/')]
    public function fileShouldExist(string $path): void
    {
        Assert::assertFileExists($this->workingDir . DIRECTORY_SEPARATOR . $path);
    }




    #[Given('that I have a valid path as a string')]
    public function thatIHaveAValidPathAsAString(): void
    {
        $this->pathValue = '/path/to/file.txt';
    }

    #[When('I pass it as a parameter to the Path constructor')]
    public function iPassItAsAParameterToThePathConstructor(): void
    {
        $this->path = new Path($this->pathValue);
    }

    #[Then('I get a new Path object')]
    public function iGetANewPathObject(): void
    {
        Assert::assertTrue($this->path instanceof Path);
    }

    #[Given('that I have a Path object')]
    public function thatIHaveAPathObject(): void
    {
        $this->path = new Path('/path/to/file.txt');
    }

    #[When('I cast it into a string')]
    public function iCastItIntoAString(): void
    {
        $this->pathValue = (string)$this->path;
    }

    #[Then('I get the path that this object took as a constructor\'s parameter')]
    public function iGetThePathThatThisObjectTookAsAConstructorsParameter(): void
    {
        Assert::assertEquals('/path/to/file.txt', $this->pathValue);
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function createFile(string $filename, string $content): void
    {
        $path = dirname($filename);
        $this->createDirectory($path);

        file_put_contents($filename, $content);
    }

    private function moveToNewPath(string $path): void
    {
        $newWorkingDir = $this->workingDir . '/' . $path;
        if (!file_exists($newWorkingDir)) {
            mkdir($newWorkingDir, 0777, true);
        }

        $this->workingDir = $newWorkingDir;
    }

    private static function clearDirectory(string $path): void
    {
        $files = scandir($path);
        array_shift($files);
        array_shift($files);

        foreach ($files as $file) {
            $file = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file)) {
                self::clearDirectory($file);
            } else {
                unlink($file);
            }
        }
    }
}
