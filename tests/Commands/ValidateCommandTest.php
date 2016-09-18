<?php

namespace Stolt\LeanPackage\Tests\Commands;

use Mockery;
use Mockery\MockInterface;
use phpmock\MockBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\ValidateCommand;
use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;
use Stolt\LeanPackage\Tests\TestCase;

class ValidateCommandTest extends TestCase
{
    /**
     * @var \Symfony\Component\Console\Application
     */
    private $application;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        $this->setUpTemporaryDirectory();
        if (!defined('WORKING_DIRECTORY')) {
            define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }
        $this->application = $this->getApplication();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown()
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesSuggestsCreation()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            '.travis.yml',
            '.buildignore',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.buildignore export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function failingGitattributesFilesCreationReturnsExpectedStatusCode()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Creation of .gitattributes file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);

        $mock->disable();
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesWithCreationOptionCreatesOne()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Created a .gitattributes file with the shown content:
* text=auto eol=lf

CONDUCT.md export-ignore
specs/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     */
    public function validGitattributesReturnsExpectedStatusCode()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'foo.txt',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
.buildignore export-ignore
foo.txt export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     */
    public function invalidGitattributesReturnsExpectedStatusCode()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
specs/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore export-ignore
.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     */
    public function optionalGlobPatternIsApplied()
    {
        $artifactFilenames = [
            'CONDUCT.rst',
            '.travis.yml',
            '.buildignore',
            'mock.pyc',
            'testrunner.py',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['dist']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern' => '{.*,*.rst,*.py[cod],testrunner.py,dist}*',
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.buildignore export-ignore
.travis.yml export-ignore
CONDUCT.rst export-ignore
dist/ export-ignore
mock.pyc export-ignore
testrunner.py export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     */
    public function usageOfInvalidGlobFailsValidation()
    {
        $failingGlobPattern = '{single-pattern*}';
        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern' => $failingGlobPattern,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern '{$failingGlobPattern}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function overwriteOptionOnNonExistentGitattributesFileImplicatesCreate()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--overwrite' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Created a .gitattributes file with the shown content:
* text=auto eol=lf

CONDUCT.md export-ignore
specs/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     */
    public function leanArchiveIsConsideredLean()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(true);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered lean.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     */
    public function notLeanArchiveIsNotConsideredLeanPlural()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(false);

        $mock->shouldReceive('getFoundUnexpectedArchiveArtifacts')
            ->twice()
            ->withAnyArgs()
            ->andReturn(['.gitignore', '.travis.yml']);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifacts slipped in:
.gitignore
.travis.yml


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function notLeanArchiveIsNotConsideredLeanSingular()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(false);

        $mock->shouldReceive('getFoundUnexpectedArchiveArtifacts')
            ->twice()
            ->withAnyArgs()
            ->andReturn(['.gitignore']);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifact slipped in:
.gitignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function impossibilityToResolveExpectedGitattributesFileContentIsInfoed()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Analyser[getExpectedGitattributesContent]'
        );
        $mock->shouldReceive('getExpectedGitattributesContent')
            ->once()
            ->withAnyArgs()
            ->andReturn('');

        $application = $this->getApplicationWithMockedAnalyser($mock);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Unable to resolve expected .gitattributes content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function invalidDirectoryAgumentReturnsExpectedStatusCode()
    {
        $nonExistentDirectoryOrFile = WORKING_DIRECTORY
            . DIRECTORY_SEPARATOR
            . 'non-existent-directory-or-file';

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $nonExistentDirectoryOrFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided directory '{$nonExistentDirectoryOrFile}' does not exist or is not a directory.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 8 (https://github.com/raphaelstolt/lean-package-validator/issues/8)
     * @dataProvider optionProvider
     */
    public function incompleteGitattributesFileIsOverwritten($option)
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = ['phpspec.yml.dist', 'version-increase-command'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            $option => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Overwrote it with the shown content:
* text=auto eol=lf

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     * @ticket 8 (https://github.com/raphaelstolt/lean-package-validator/issues/8)
     */
    public function failingGitattributesFilesOverwriteReturnsExpectedStatusCode()
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = ['phpspec.yml.dist'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Overwrite of .gitattributes file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);

        $mock->disable();
    }

    /**
     * @test
     * @ticket 6 (https://github.com/raphaelstolt/lean-package-validator/issues/6)
     */
    public function strictOrderOfExportIgnoresCanBeEnforced()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
Phulpfile export-ignore
.buildignore export-ignore
phpspec.yml.dist
specs/ export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--enforce-strict-order' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore export-ignore
.gitattributes export-ignore
phpspec.yml.dist export-ignore
Phulpfile export-ignore
specs/ export-ignore
phpspec.yml.dist


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @return array
     */
    public function optionProvider()
    {
        return [
            ['--overwrite'],
            ['--create']
        ];
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplicationWithMockedAnalyser(MockInterface $mockedAnalyser)
    {
        $application = new Application();

        $archive = new Archive(
            $this->temporaryDirectory,
            basename($this->temporaryDirectory)
        );

        $analyserCommand = new ValidateCommand(
            $mockedAnalyser,
            new Validator($archive)
        );

        $application->add($analyserCommand);

        return $application;
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplicationWithMockedArchiveValidator(MockInterface $mockedArchiveValidator)
    {
        $application = new Application();

        $analyserCommand = new ValidateCommand(
            new Analyser,
            $mockedArchiveValidator
        );

        $application->add($analyserCommand);

        return $application;
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication()
    {
        $application = new Application();
        $archive = new Archive(
            $this->temporaryDirectory,
            basename($this->temporaryDirectory)
        );

        $analyserCommand = new ValidateCommand(
            new Analyser,
            new Validator($archive)
        );

        $application->add($analyserCommand);

        return $application;
    }
}
