<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - PipraPay</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #3BB77E;
            --primary-light: rgba(59, 183, 126, 0.1);
            --primary-dark: #2e8f63;
        }
        
        body {
            background-color: #f8f9fa;
            height: 100vh;
            color: #212529;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .error-container {
            background-color: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--primary);
            max-width: 650px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, var(--primary-light), transparent 60%);
            z-index: 0;
        }
        
        .error-icon {
            font-size: 5.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            position: relative;
            animation: pulse 2s infinite;
        }
        
        .btn-primary-custom {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary-custom:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(59, 183, 126, 0.3);
        }
        
        .btn-primary-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }
        
        .btn-primary-custom:hover::before {
            left: 100%;
        }
        
        .error-code {
            font-size: 4.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary), #4acf8f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .error-divider {
            width: 60px;
            height: 4px;
            background: linear-gradient(to right, var(--primary), #4acf8f);
            margin: 1.5rem auto;
            border-radius: 2px;
            opacity: 0.8;
        }
        
        .search-box {
            position: relative;
            max-width: 400px;
            margin: 2rem auto;
        }
        
        .search-box input {
            padding-left: 3rem;
            border-radius: 50px;
            border: 1px solid #e0e0e0;
            height: 50px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .search-box i {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
        }
        
        .error-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .error-links a {
            color: #666;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 0.9rem;
        }
        
        .error-links a:hover {
            color: var(--primary);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            background-color: var(--primary);
            border-radius: 50%;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .error-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            
            .error-code {
                font-size: 3.5rem;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container h-100">
        <div class="row h-100 align-items-center justify-content-center">
            <div class="col-12">
                <div class="error-container animate__animated animate__fadeIn">
                    <div class="floating-shapes" id="shapes"></div>
                    
                    <i class="fas fa-exclamation-triangle error-icon"></i>
                    <div class="error-code">404</div>
                    <h2 class="mb-3 fw-bold"><?php echo htmlspecialchars($error_title ?? 'Page Not Found');?></h2>
                    <div class="error-divider"></div>
                    <p class="mb-4 text-muted"><?php echo htmlspecialchars($error_description ?? 'The page you requested could not be found. It might have been moved or deleted.');?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Create floating shapes
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('shapes');
            const shapesCount = 8;
            
            for (let i = 0; i < shapesCount; i++) {
                const shape = document.createElement('div');
                shape.classList.add('shape');
                
                // Random size between 20px and 100px
                const size = Math.random() * 80 + 20;
                shape.style.width = `${size}px`;
                shape.style.height = `${size}px`;
                
                // Random position
                shape.style.left = `${Math.random() * 100}%`;
                shape.style.top = `${Math.random() * 100}%`;
                
                // Random opacity
                shape.style.opacity = Math.random() * 0.1 + 0.05;
                
                // Random animation
                const duration = Math.random() * 20 + 10;
                const delay = Math.random() * 5;
                shape.style.animation = `float ${duration}s linear ${delay}s infinite`;
                
                container.appendChild(shape);
            }
            
            // Add floating animation dynamically
            const style = document.createElement('style');
            style.textContent = `
                @keyframes float {
                    0% { transform: translate(0, 0) rotate(0deg); }
                    25% { transform: translate(${Math.random() * 20 - 10}px, ${Math.random() * 20 - 10}px) rotate(${Math.random() * 10 - 5}deg); }
                    50% { transform: translate(${Math.random() * 40 - 20}px, ${Math.random() * 40 - 20}px) rotate(${Math.random() * 20 - 10}deg); }
                    75% { transform: translate(${Math.random() * 20 - 10}px, ${Math.random() * 20 - 10}px) rotate(${Math.random() * 10 - 5}deg); }
                    100% { transform: translate(0, 0) rotate(0deg); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>