var http = require('http')
var porta = process.env.PORT_APP || 3001


http.createServer((req, res) => {
  res.writeHead(200, {'Content-Type': 'text/plain'})
  res.end('Hello Node')
}).listen(porta)


console.log('Servidor rodando na porta: '+ porta)
