import {JobStatus, Workflow, WorkflowStatus} from "../types";

export const workflowSample: Workflow = {
    name: 'Sample workflow',
    status: WorkflowStatus.Started,
    startedAt: '2023-05-24T10:22:25.495639+00:00',
    id: '123',
    stages: [
        {
            jobs: [
                {
                    name: 'init',
                    stateId: 'init-0',
                    jobId: 'init',
                    status: JobStatus.Success,
                    duration: '0.053s',
                    triggeredAt: '2023-05-24T10:22:25.495639+00:00',
                    startedAt: '2023-05-24T10:24:25.495639+00:00',
                    endedAt: '2023-05-24T10:25:25.495639+00:00',
                    inputs: {
                        foo: 'bar',
                        baz: 42,
                    },
                    outputs: {
                        foo: 'bar',
                        results: [
                            {ID: '1', Name: 'Alice'},
                            {ID: '2', Name: 'Bob'},
                        ]
                    }
                },
                {
                    name: 'skipped',
                    jobId: 'skipped',
                    status: JobStatus.Skipped,
                },
            ],
        },
        {
            jobs: [
                {
                    name: 'Convert A long name overflow aaa bbb cccc dddddd',
                    id: 'convert',
                    status: JobStatus.Running,
                    needs: [
                        'init',
                    ],
                    triggeredAt: '2023-05-24T10:22:25.495639+00:00',
                    startedAt: '2023-05-24T10:24:25.495639+00:00',
                },
                {
                    name: 'Notify A long name overflow aaa bbb cccc dddddd',
                    id: 'notify',
                    status: JobStatus.Success,
                    duration: '2.891s',
                    needs: [
                        'init',
                    ],
                    if: `env.toto == 42`,
                    triggeredAt: '2023-05-24T10:22:25.495639+00:00',
                    startedAt: '2023-05-24T10:24:25.495639+00:00',
                    endedAt: '2023-05-24T10:25:25.495639+00:00',
                },
                {
                    name: 'failed',
                    id: 'failed',
                    status: JobStatus.Failure,
                    duration: '2.987s',
                    needs: [
                        'init',
                    ],
                    triggeredAt: '2023-05-24T10:22:25.495639+00:00',
                    startedAt: '2023-05-24T10:24:25.495639+00:00',
                    endedAt: '2023-05-24T10:25:25.495639+00:00',
                    errors: [
                        'Call to undefined function App\\Integration\\Blurhash\\imagecreatefromstring() [/srv/app/src/Integration/Blurhash/BlurhashAction.php:52]\n' +
                        '#0 /srv/app/src/Integration/Blurhash/BlurhashAction.php(38): App\\Integration\\Blurhash\\BlurhashAction->getBlurhash()\n' +
                        '#1 /srv/app/__lib/workflow/src/Executor/JobExecutor.php(186): App\\Integration\\Blurhash\\BlurhashAction->handle()\n' +
                        '#2 /srv/app/__lib/workflow/src/Executor/JobExecutor.php(149): Alchemy\\Workflow\\Executor\\JobExecutor->Alchemy\\Workflow\\Executor\\{closure}()\n' +
                        '#3 /srv/app/__lib/workflow/src/Executor/JobExecutor.php(112): Alchemy\\Workflow\\Executor\\JobExecutor->runJob()\n' +
                        '#4 /srv/app/__lib/workflow/src/Executor/PlanExecutor.php(34): Alchemy\\Workflow\\Executor\\JobExecutor->executeJob()\n' +
                        '#5 /srv/app/__lib/workflow/src/Consumer/JobConsumer.php(30): Alchemy\\Workflow\\Executor\\PlanExecutor->executePlan()\n' +
                        '#6 /srv/app/vendor/arthem/rabbit-bundle/Consumer/EventConsumer.php(91): Alchemy\\Workflow\\Consumer\\JobConsumer->handle()\n' +
                        '#7 /srv/app/vendor/arthem/rabbit-bundle/Command/DirectConsumerCommand.php(41): Arthem\\Bundle\\RabbitBundle\\Consumer\\EventConsumer->processMessage()\n' +
                        '#8 /srv/app/vendor/symfony/console/Command/Command.php(298): Arthem\\Bundle\\RabbitBundle\\Command\\DirectConsumerCommand->execute()\n' +
                        '#9 /srv/app/vendor/symfony/console/Application.php(1058): Symfony\\Component\\Console\\Command\\Command->run()\n' +
                        '#10 /srv/app/vendor/symfony/framework-bundle/Console/Application.php(96): Symfony\\Component\\Console\\Application->doRunCommand()\n' +
                        '#11 /srv/app/vendor/symfony/console/Application.php(301): Symfony\\Bundle\\FrameworkBundle\\Console\\Application->doRunCommand()\n' +
                        '#12 /srv/app/vendor/symfony/framework-bundle/Console/Application.php(82): Symfony\\Component\\Console\\Application->doRun()\n' +
                        '#13 /srv/app/vendor/symfony/console/Application.php(171): Symfony\\Bundle\\FrameworkBundle\\Console\\Application->doRun()\n' +
                        '#14 /srv/app/bin/console(43): Symfony\\Component\\Console\\Application->run()\n' +
                        '#15 {main}',
                    ]
                },
                {
                    name: 'errored',
                    id: 'errored',
                    status: JobStatus.Error,
                    duration: '-',
                    needs: [
                        'init',
                    ],
                    triggeredAt: '2023-05-24T10:22:25.495639+00:00',
                    errors: [
                        'Error in if condition',
                    ]
                },
            ],
        },
        {
            jobs: [
                {
                    name: 'close',
                    id: 'close',
                    needs: [
                        'convert',
                        'notify',
                    ],
                },
            ],
        }
    ],
};
