<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance</title>
    <link rel="icon" type="image/x-icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3BB77E;
            --secondary-color: #2c3e50;
            --text-color: #555;
            --light-bg: #f9fafc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            margin: 0;
            display: flex;
            justify-content: center;
            text-align: center;
            line-height: 1.6;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            max-width: 800px;
            width: 90%;
            margin: 20px auto;
        }
        
        .maintenance-icon {
            font-size: 5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite ease-in-out;
        }
        
        h1 {
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        p.lead {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text-color);
        }
        
        .progress-container {
            margin: 2rem 0;
        }
        
        .progress {
            height: 12px;
            border-radius: 6px;
            background: #e9ecef;
        }
        
        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 6px;
            width: 75%; /* Adjust progress percentage */
            animation: progressAnimation 2s ease-in-out infinite;
        }
        
        .countdown {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .countdown-item {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            min-width: 80px;
            transition: transform 0.3s;
        }
        
        .countdown-item:hover {
            transform: translateY(-5px);
        }
        
        .countdown-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .countdown-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .social-links {
            margin: 2rem 0;
        }
        
        .social-links a {
            color: var(--text-color);
            margin: 0 12px;
            font-size: 1.4rem;
            transition: color 0.3s, transform 0.3s;
        }
        
        .social-links a:hover {
            color: var(--primary-color);
            transform: scale(1.2);
        }
        
        .contact-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .contact-info a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Animations */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes progressAnimation {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 2rem 1.5rem;
                width: 95%;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            p.lead {
                font-size: 1rem;
            }
            
            .countdown-item {
                padding: 0.8rem 1rem;
                min-width: 70px;
            }
            
            .countdown-value {
                font-size: 1.6rem;
            }
        }
        
        @media (max-width: 576px) {
            .maintenance-container {
                border-radius: 0;
                box-shadow: none;
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            
            .maintenance-icon {
                font-size: 4rem;
            }
            
            .countdown {
                gap: 0.5rem;
            }
            
            .countdown-item {
                padding: 0.6rem 0.8rem;
                min-width: 60px;
            }
            
            .countdown-value {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Under Maintenance</h1>
        <p class="lead">We're upgrading our system to serve you better. Please check back soon!</p>
        
        <div class="progress-container">
            <div class="progress">
                <div class="progress-bar progress-bar-striped" role="progressbar"></div>
            </div>
            <small id="progress-text" class="text-muted">0% Completed</small>
        </div>
        
        <p>Estimated time remaining:</p>
        
        <div class="countdown">
            <div class="countdown-item">
                <div class="countdown-value" id="days">0</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value" id="hours">0</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value" id="minutes">30</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-item">
                <div class="countdown-value" id="seconds">0</div>
                <div class="countdown-label">Seconds</div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const totalTime = 5 * 60 * 1000; // 30 minutes in milliseconds
        const endDate = new Date().getTime() + totalTime;
    
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endDate - now;
    
            if (distance < 0) {
                clearInterval(countdownTimer);
                document.querySelector(".progress-bar").style.width = "100%";
                document.querySelector(".progress-bar").classList.remove("progress-bar-striped");
                document.getElementById("progress-text").textContent = "Maintenance Complete!";
                document.getElementById("days").textContent = '00';
                document.getElementById("hours").textContent = '00';
                document.getElementById("minutes").textContent = '00';
                document.getElementById("seconds").textContent = '00';
                return;
            }
    
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
            document.getElementById("days").textContent = days.toString().padStart(2, '0');
            document.getElementById("hours").textContent = hours.toString().padStart(2, '0');
            document.getElementById("minutes").textContent = minutes.toString().padStart(2, '0');
            document.getElementById("seconds").textContent = seconds.toString().padStart(2, '0');
    
            const percentComplete = 100 - ((distance / totalTime) * 100);
            document.querySelector(".progress-bar").style.width = percentComplete.toFixed(1) + "%";
            document.getElementById("progress-text").textContent = percentComplete.toFixed(0) + "% Completed";
        }
    
        updateCountdown(); // Initial call
        const countdownTimer = setInterval(updateCountdown, 1000);
    </script>
</body>
</html>