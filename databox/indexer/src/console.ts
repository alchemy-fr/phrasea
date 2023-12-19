import {Command} from 'commander';
import indexCommand from './command/index.js';
import listCommand from "./command/list";
import watchCommand from "./command/watch";

const program = new Command();

program
    .name('indexer')
    .description('Databox Indexer')
    .version('1.0.0');

program
    .command('index')
    .description('Index a location')
    .argument('<location-name>', 'The location to index')
    .option(
        '-n, --create-new-workspace',
        'Remove existing workspace and create a new empty one',
        false
    )
    .action(indexCommand);

program
    .command('watch')
    .description('Watch locations')
    .option(
        '-l, --location',
        'List locations to watch',
        false
    )
    .action(watchCommand);

program
    .command('list')
    .description('List locations')
    .action(listCommand);

program.parse();
