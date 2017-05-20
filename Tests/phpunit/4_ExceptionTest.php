<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
 * Tests the MigrationAbortedException, as well as direct manipulation of the migration service
 */
class ExceptionTest extends CommandTest implements ExecutorInterface
{
    public function testMigrationAbortedException()
    {
        $ms = $this->container->get('ez_migration_bundle.migration_service');
        $ms->addExecutor($this);
        $md = new MigrationDefinition(
            'exception_test.json',
            '/dev/null',
            json_encode(array(array('type' => 'abort')))
        );
        $ms->executeMigration($md);

        $m = $ms->getMigration('exception_test.json');
        $this->assertEquals(Migration::STATUS_DONE, $m->status, 'Migration supposed to be aborted but in unexpected state');
        $this->assertContains('Oh yeah', $m->executionError, 'Migration aborted but its exception message lost');
    }

    /**
     * @param string $filePath
     * @dataProvider goodDSLProvider
     */
    public function testExecuteGoodDSL($filePath = '')
    {
        $bundles = $this->container->getParameter('kernel.bundles');
        $ms = $this->container->get('ez_migration_bundle.migration_service');

        if ($filePath == '') {
            $this->markTestSkipped();
            return;
        }

        // Make sure migration is not in the db: delete it, ignoring errors
        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $this->app->run($input, $this->output);
        $this->fetchOutput();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegexp('?Added migration?', $output);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);

        $count3 = BeforeStepExecutionListener::getExecutions();
        $count4 = StepExecutedListener::getExecutions();
        $this->assertEquals($count1 + 2, $count3, "Migration not suspended/canceled: executed incorrect number of steps");
        $this->assertEquals($count2 + 1, $count4, "Migration not suspended/canceled: executed incorrect number of steps");

        $m = $ms->getMigration(basename($filePath));
        $this->assertThat(
            $m->status,
            $this->logicalOr(
                $this->equalTo(Migration::STATUS_SUSPENDED),
                $this->equalTo(Migration::STATUS_DONE)
            ),
            'Migration supposed to be aborted/suspended but in unexpected state'
        );

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
    }

    public function goodDSLProvider()
    {
        $dslDir = $this->dslDir.'/exceptions';
        if (!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach (scandir($dslDir) as $fileName) {
            $filePath = $dslDir . '/' . $fileName;
            if (is_file($filePath)) {
                $out[] = array($filePath);
            }
        }
        return $out;
    }

    public function supportedTypes()
    {
        return array('abort');
    }

    public function execute(MigrationStep $step)
    {
        throw new MigrationAbortedException('Oh yeah');
    }
}