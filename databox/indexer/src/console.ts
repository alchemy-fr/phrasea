import {Command, Option} from 'commander';
import indexCommand from './command/index.js';
import listCommand from './command/list';
import watchCommand from './command/watch';

const program = new Command();

program.name('console').description('Databox Indexer').version('1.0.0');

const debugOption = new Option('--debug', 'Debug mode')
    .default(false);

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
    .action(indexCommand);

program
    .command('watch')
    .description('Watch locations')
    .option('-l, --location', 'List locations to watch', false)
    .addOption(debugOption)
    .action(watchCommand);

program.command('list').description('List locations').action(listCommand);

program.parse();
