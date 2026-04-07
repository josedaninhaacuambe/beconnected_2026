<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Código de verificação Beconnect</title>
</head>
<body style="margin:0;padding:0;background:#0f1923;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1923;padding:40px 20px;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#1a2535;border-radius:16px;overflow:hidden;max-width:100%;">

        <!-- Header -->
        <tr>
          <td align="center" style="background:#D4A017;padding:24px;">
            <div style="width:56px;height:56px;background:#1a2535;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px;">
              <span style="color:#D4A017;font-weight:900;font-size:20px;">BC</span>
            </div>
            <p style="margin:0;color:#1a2535;font-weight:900;font-size:20px;letter-spacing:1px;">BECONNECT</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 40px;text-align:center;">
            <p style="color:#e8d5a0;font-size:16px;margin:0 0 8px;">Olá, <strong>{{ $userName }}</strong> 👋</p>
            <p style="color:#8a9ab0;font-size:14px;margin:0 0 28px;line-height:1.6;">
              Para confirmar o teu email e activar a conta Beconnect, insere o código abaixo:
            </p>

            <!-- OTP Code -->
            <div style="background:#0f1923;border:2px solid #D4A017;border-radius:12px;padding:20px 32px;display:inline-block;margin-bottom:28px;">
              <span style="color:#D4A017;font-size:40px;font-weight:900;letter-spacing:12px;font-family:monospace;">{{ $otp }}</span>
            </div>

            <p style="color:#8a9ab0;font-size:13px;margin:0 0 6px;">⏱ Este código expira em <strong style="color:#e8d5a0;">10 minutos</strong>.</p>
            <p style="color:#8a9ab0;font-size:13px;margin:0;">Se não criaste esta conta, ignora este email.</p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="border-top:1px solid #2a3545;padding:16px 40px;text-align:center;">
            <p style="color:#4a5568;font-size:12px;margin:0;">© {{ date('Y') }} Beconnect · Mercado Virtual de Moçambique</p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
