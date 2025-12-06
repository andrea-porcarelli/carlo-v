<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benvenuto</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #154A53 0%, #0E0D0A 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .container {
            text-align: center;
            padding: 2rem;
            width: 100%;
            max-width: 600px;
        }

        .logo-wrapper {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .logo {
            width: 100%;
            height: auto;
            max-width: 400px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .logo-wrapper {
                padding: 2rem;
                border-radius: 15px;
            }

            .logo {
                max-width: 300px;
            }
        }

        @media (max-width: 480px) {
            .logo-wrapper {
                padding: 1.5rem;
                border-radius: 10px;
            }

            .logo {
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrapper">
            <img src="{{ asset('app/images/cropped-cropped-misuraca_bianco.png') }}" alt="Logo" class="logo">
        </div>
    </div>
</body>
</html>
