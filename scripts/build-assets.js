#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const ASSETS_DIR = path.join(__dirname, '..', 'assets');
const NODE_MODULES = path.join(__dirname, '..', 'node_modules');

const ASSETS = {
  'tui-image-editor/dist/tui-image-editor.min.js': 'tui-image-editor.min.js',
  'tui-image-editor/dist/tui-image-editor.min.css': 'tui-image-editor.min.css',
  'fabric/dist/fabric.min.js': 'fabric.min.js',
  'file-saver/FileSaver.min.js': 'FileSaver.min.js',
  'tui-code-snippet/dist/tui-code-snippet.min.js': 'tui-code-snippet.min.js',
  'tui-color-picker/dist/tui-color-picker.min.js': 'tui-color-picker.min.js',
  'tui-color-picker/dist/tui-color-picker.min.css': 'tui-color-picker.min.css'
};

if (!fs.existsSync(NODE_MODULES)) {
  console.error('Run npm install first');
  process.exit(1);
}

if (!fs.existsSync(ASSETS_DIR)) {
  fs.mkdirSync(ASSETS_DIR, { recursive: true });
}

let copied = 0;
for (const [src, dest] of Object.entries(ASSETS)) {
  const srcPath = path.join(NODE_MODULES, src);
  const destPath = path.join(ASSETS_DIR, dest);

  if (fs.existsSync(srcPath)) {
    fs.copyFileSync(srcPath, destPath);
    copied++;
  } else {
    console.error(`Missing: ${src}`);
  }
}

console.log(`Copied ${copied}/${Object.keys(ASSETS).length} assets`);