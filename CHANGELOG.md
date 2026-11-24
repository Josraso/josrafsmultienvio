# Changelog

## 1.0 — 2026-11-24

Primera version.

- Permite dar de alta tantas cuentas de correo de salida (SMTP) como se quiera, ademas de la cuenta de la propia instalacion. Se gestionan en Panel de control > Email > pestaña "Cuentas de envio", donde se pueden crear, editar, duplicar y borrar.
- Al enviar cualquier documento, el desplegable "Desde" muestra todas las cuentas activas. La elegida es la que manda el correo: su servidor, su usuario, su contraseña, su nombre de remitente, su firma, sus copias y su direccion de respuesta.
- Cada cuenta puede llevar asignado un formato de documento. Si lo lleva, el PDF que se adjunta al correo se vuelve a generar con ese formato antes de salir, asi que el cliente recibe el documento con el logo, el titulo y el texto de pie de esa cuenta, sin tener que elegir nada a mano.
- Opcion "recordar el formato usado" (Panel de control > Email > pestaña "Opciones del multienvio"): cuando esta activada, el documento se queda marcado con el formato con el que se envio, de forma que cualquier copia que se imprima o se vuelva a enviar despues sale con el mismo logo que recibio el cliente.
- Las contraseñas de las cuentas se guardan cifradas (AES-256-GCM). El plugin se encarga el solo de la clave de cifrado, sin tener que tocar nada a mano, y en su pantalla indica si por algun motivo no ha podido cifrarlas.
- Boton de prueba por cuenta: comprueba la conexion con el servidor de correo y deja apuntada la fecha y el resultado del ultimo intento, sin impedir guardar la configuracion si el servidor esta caido en ese momento.
