const proxy = require('http-proxy-middleware');

module.exports = function(app) {
  app.use(proxy('/wp-json', { target: 'http://localhost:8080/' }));
};
