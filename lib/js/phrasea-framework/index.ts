import {AppProvider} from './src/AppProvider';
import {initApp} from './src/init';
import {WindowConfig} from '@alchemy/core';
import ThemeEditorProvider from './src/Theme/ThemeEditor/ThemeEditorProvider';
import MuiThemeEditor from './src/Theme/ThemeEditor/MuiThemeEditor';
import ThemeEditorContext from './src/Theme/ThemeEditor/ThemeEditorContext';
import {createCachedThemeOptions, themes} from './src/Theme/theme';
import {AppGlobalTheme} from './src/Theme/AppGlobalTheme';

export {AppProvider, initApp, createCachedThemeOptions, themes, AppGlobalTheme};
export {ThemeEditorProvider, MuiThemeEditor, ThemeEditorContext};

export type {WindowConfig};

export * from './src/Theme/types';
