/**
 * Firma el token del widget (JWT HS256) en Node.js, sin dependencias.
 * Solo en el SERVIDOR: el secreto nunca debe llegar al navegador.
 *
 * Uso (Express):
 *   const { widgetToken } = require("./widget-token");
 *   app.get("/token-endpoint", requireLogin, (req, res) => {
 *     res.set("Cache-Control", "no-store").json({
 *       token: widgetToken({
 *         email: req.user.email, name: req.user.nombre, phone: req.user.telefono,
 *         company: req.user.codCompania, branch: req.user.codSucursal
 *       })
 *     });
 *   });
 */
const crypto = require("crypto");

const b64url = (input) => Buffer.from(input).toString("base64url");

function widgetToken(datos, vigenciaSegundos = 600) {
  const secret = process.env.WIDGET_SECRET || "";

  if (secret.length < 32) {
    throw new Error("WIDGET_SECRET no está configurado.");
  }

  const ahora = Math.floor(Date.now() / 1000);
  const claims = {
    ...Object.fromEntries(Object.entries(datos).filter(([, v]) => v !== null && v !== undefined && v !== "")),
    iat: ahora,
    exp: ahora + Math.max(60, Math.min(3600, vigenciaSegundos)),
  };

  const header = b64url(JSON.stringify({ alg: "HS256", typ: "JWT" }));
  const payload = b64url(JSON.stringify(claims));
  const firma = crypto.createHmac("sha256", secret).update(`${header}.${payload}`).digest("base64url");

  return `${header}.${payload}.${firma}`;
}

module.exports = { widgetToken };
