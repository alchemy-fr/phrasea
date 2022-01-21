import {Config} from "./types/config";

const fs = require('fs');

function loadConfig(): Config
{
    return JSON.parse(fs.readFileSync(__dirname+'/../config/config.json').toString());
}

export const config = loadConfig();
