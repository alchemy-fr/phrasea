import {Command} from 'commander';
import indexCommand from "./command";

const program = new Command();

program
    .name('list')
    .description('Databox indexer')
    .version('1.0.0');

program.command('index')
    .description('Index a location')
    .argument('<location-name>', 'The location to index')
    .option('-n, --create-new-workspace', 'Remove existing workspace and create a new empty one', false)
    .action(indexCommand);

program.parse();
