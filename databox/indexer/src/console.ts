import './init';
import {Command, Option} from 'commander';
import indexCommand from './command/index.js';
import listCommand from './command/list';
import watchCommand from './command/watch';
import indexAllCommand from './command/indexAll';

const program = new Command();

program.name('console').description('Databox Indexer').version('1.0.0');

const debugOption = new Option('--debug', 'Debug mode').default(false);
const noServerOption = new Option(
    '--no-server',
    'Do not start the asset HTTP server; exit once the indexation is done'
);

program
    .command('index')
    .description('Index a location')
    .argument('<location-name>', 'The location to index')
    .option(
        '-n, --create-new-workspace',
        'Remove existing workspace and create a new empty one',
        false
    )
    .addOption(debugOption)
    .addOption(noServerOption)
    .action(indexCommand);

program
    .command('index-all')
    .description('Index all locations')
    .option(
        '-n, --create-new-workspace',
        'Remove existing workspace and create a new empty one',
        false
    )
    .addOption(debugOption)
    .addOption(noServerOption)
    .action(indexAllCommand);

program
    .command('watch')
    .description('Watch locations')
    .option('-l, --location', 'List locations to watch', false)
    .addOption(debugOption)
    .action(watchCommand);

program.command('list').description('List locations').action(listCommand);

program.parse();
