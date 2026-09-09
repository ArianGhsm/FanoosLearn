(function (global) {
  'use strict';
  const UI = global.FanoosProductUI || {};
  global.FanoosProductUI = UI;
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);
