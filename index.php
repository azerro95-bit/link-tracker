<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محول الصوت الجزائري الاحترافي بالذكاء الاصطناعي</title>
    <!-- Tailwind CSS للتصميم الجبار -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between font-sans">

    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800 py-4 px-6 shadow-lg">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                <i class="fa-solid fa-microphone-lines ml-2"></i> صوتك الجزائري الذكي
            </h1>
            <span class="text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full font-medium">النسخة الاحترافية</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-xl mx-auto p-6 w-full my-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
            <!-- تأثير إضاءة خلفية خفيف -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>
            
            <div class="text-center mb-8">
                <h2 class="text-2xl font-extrabold text-white mb-2">حول صوتك إلى صوت طبيعي 100%</h2>
                <p class="text-slate-400 text-sm">بنبرة جزائرية أصيلة وبأعلى جودة ممكنة عبر الذكاء الاصطناعي.</p>
            </div>

            <!-- نموذج الرفع أو التسجيل -->
            <form id="audioForm" class="space-y-6" enctype="multipart/form-data">
                <!-- اختيار الملف أو التسجيل -->
                <div class="border-2 border-dashed border-slate-700 hover:border-emerald-500 transition rounded-xl p-6 text-center cursor-pointer bg-slate-950/50">
                    <input type="file" id="audioFile" name="audio" accept="audio/*" class="hidden">
                    <label for="audioFile" class="cursor-pointer block">
                        <i class="fa-solid fa-cloud-arrow-up text-4xl text-emerald-400 mb-3"></i>
                        <p class="text-sm font-medium text-slate-300">اضغط لرفع ملف صوتي أو اسحبه هنا</p>
                        <p class="text-xs text-slate-500 mt-1">MP3, WAV, OGG (الحد الأقصى 10 ميجابايت)</p>
                    </label>
                </div>

                <!-- خيارات التحويل -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">اختر النموذج المستهدف:</label>
                    <select class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-emerald-500 transition">
                        <option value="algerian_girl_1">صوت فتاة جزائرية (طبيعي وناعم)</option>
                        <option value="algerian_girl_2">صوت فتاة جزائرية (حماسي)</option>
                    </select>
                </div>

                <!-- زر التحويل -->
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-cyan-500 text-slate-950 font-bold py-3.5 px-6 rounded-xl shadow-lg hover:opacity-90 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> بدء تحويل الصوت الآن
                </button>
            </form>

            <!-- منطقة النتيجة والتحميل / المشاركة -->
            <div id="resultSection" class="hidden mt-8 p-4 bg-slate-950 rounded-xl border border-slate-800">
                <h3 class="text-sm font-semibold text-emerald-400 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> تم التحويل بنجاح!
                </h3>
                <audio id="outputAudio" controls class="w-full mb-4"></audio>
                <div class="flex gap-3">
                    <a id="downloadBtn" href="#" class="flex-1 bg-slate-800 hover:bg-slate-700 text-center py-2.5 rounded-lg text-sm font-medium transition">
                        <i class="fa-solid fa-download ml-1"></i> تحميل المقطع
                    </a>
                    <button onclick="shareToMessenger()" class="flex-1 bg-blue-600 hover:bg-blue-500 text-center py-2.5 rounded-lg text-sm font-medium transition text-white">
                        <i class="fa-brands fa-facebook-messenger ml-1"></i> إرسال لماسنجر
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-4 text-xs text-slate-600 border-t border-slate-900">
        powred by azeez
    </footer>

    <script>
        document.getElementById('audioForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // محاكاة عملية التحويل (سيتم ربطها بسكربت البايثون لاحقاً)
            alert('جاري معالجة الصوت بالذكاء الاصطناعي...');
        });

        function shareToMessenger() {
            // استخدام Web Share API أو رابط Deep Link لماسنجر
            if (navigator.share) {
                navigator.share({
                    title: 'صوتي المعدل',
                    text: 'اسمع هذا الصوت المحول بالذكاء الاصطناعي!',
                    url: window.location.href,
                }).catch(console.error);
            } else {
                alert('عذراً، خاصية المشاركة المباشرة غير مدعومة في متصفحك، يمكنك تحميل الملف وإرساله يدوياً.');
            }
        }
    </script>
</body>
</html>
