rHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> تحويل إلى تسجيل صوتي حي (Voice Note)';
                processBtn.disabled = false;
            }
        }

        async function shareAudio() {
            if (convertedFile && navigator.canShare && navigator.canShare({ files: [convertedFile] })) {
                try {
                    await navigator.share({
                        files: [convertedFile],
                        title: 'تسجيل صوتي',
                        text: 'استمع للتسجيل الصوتي:'
                    });
                } catch (err) {
                    console.log('تم إلغاء المشاركة أو غير مدعومة.');
                }
            } else {
                alert('قم بتحميل ملف OPUS وإرساله في واتساب/ماسنجر كـ Document/Audio وسيتعامل معه كـ Voice Note.');
            }
        }
    </script>
</body>
</html>
