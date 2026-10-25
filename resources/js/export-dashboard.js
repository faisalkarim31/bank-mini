window.exportDashboardPdf = async (filename = 'dashboard.pdf') => {
    const target = document.querySelector('.fi-page');

    if (! target) {
        alert('Halaman dashboard tidak ditemukan.');
        return;
    }

    const scrollX = window.scrollX;
    const scrollY = window.scrollY;

    document.documentElement.classList.add('exporting');

    try {
        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
            import('html2canvas-pro'),
            import('jspdf'),
        ]);

        window.scrollTo(0, 0);

        await new Promise((resolve) => setTimeout(resolve, 500));

        const canvas = await html2canvas(target, {
            scale: 2,
            backgroundColor: '#fafafa',
            useCORS: true,
            logging: false,
        });

        const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const imageWidth = pageWidth - margin * 2;
        const usableHeight = pageHeight - margin * 2;

        const sliceHeight = Math.floor((usableHeight * canvas.width) / imageWidth);

        let offset = 0;
        let firstPage = true;

        while (offset < canvas.height) {
            const height = Math.min(sliceHeight, canvas.height - offset);

            const slice = document.createElement('canvas');
            slice.width = canvas.width;
            slice.height = height;
            slice.getContext('2d').drawImage(canvas, 0, offset, canvas.width, height, 0, 0, canvas.width, height);

            if (! firstPage) {
                pdf.addPage();
            }

            pdf.addImage(slice.toDataURL('image/png'), 'PNG', margin, margin, imageWidth, (height * imageWidth) / canvas.width);

            offset += height;
            firstPage = false;
        }

        pdf.save(filename);
    } catch (error) {
        console.error('Export PDF gagal:', error);

        const detail = error?.message ?? error?.type ?? 'lihat Console browser (F12)';

        alert('PDF gagal dibuat. Coba lagi.\n\nDetail: ' + detail);
    } finally {
        document.documentElement.classList.remove('exporting');
        window.scrollTo(scrollX, scrollY);
    }
};
