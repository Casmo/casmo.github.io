// This is all you.
import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';
import yaml from 'highlight.js/lib/languages/yaml';

hljs.registerLanguage('php', php);
hljs.registerLanguage('yaml', yaml);

hljs.highlightAll();