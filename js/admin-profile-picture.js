(() => {
    const dialog = document.querySelector('[data-profile-crop-dialog]');
    if (!(dialog instanceof HTMLDialogElement)) return;

    const canvas = dialog.querySelector('[data-profile-crop-canvas]');
    const zoomSlider = dialog.querySelector('[data-profile-crop-zoom]');
    const applyButton = dialog.querySelector('[data-profile-crop-apply]');
    const loadingMessage = dialog.querySelector('[data-profile-crop-loading]');
    const errorMessage = dialog.querySelector('[data-profile-crop-error]');
    const context = canvas instanceof HTMLCanvasElement ? canvas.getContext('2d') : null;
    if (!canvas || !zoomSlider || !applyButton || !loadingMessage || !errorMessage || !context) return;

    let activeInput = null;
    let sourceImage = null;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragPosition = null;
    const previewObjectUrls = new WeakMap();
    const cropSize = canvas.width;

    const showError = (message) => {
        errorMessage.textContent = message;
        errorMessage.hidden = false;
    };

    const clampOffsets = () => {
        if (!sourceImage) return;
        const scale = Math.max(cropSize / sourceImage.width, cropSize / sourceImage.height) * zoom;
        const maxX = Math.max(0, (sourceImage.width * scale - cropSize) / 2);
        const maxY = Math.max(0, (sourceImage.height * scale - cropSize) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
    };

    const renderCrop = () => {
        if (!sourceImage) return;
        clampOffsets();
        const scale = Math.max(cropSize / sourceImage.width, cropSize / sourceImage.height) * zoom;
        const width = sourceImage.width * scale;
        const height = sourceImage.height * scale;
        const x = (cropSize - width) / 2 + offsetX;
        const y = (cropSize - height) / 2 + offsetY;

        context.clearRect(0, 0, cropSize, cropSize);
        context.drawImage(sourceImage, x, y, width, height);
    };

    const closeCrop = (discardChanges) => {
        const inputToDiscard = activeInput;
        if (sourceImage && typeof sourceImage.close === 'function') sourceImage.close();
        sourceImage = null;
        activeInput = null;
        dragPosition = null;
        zoomSlider.disabled = true;
        applyButton.disabled = true;
        loadingMessage.hidden = false;
        errorMessage.hidden = true;
        errorMessage.textContent = '';
        context.clearRect(0, 0, cropSize, cropSize);

        if (discardChanges) {
            if (inputToDiscard instanceof HTMLInputElement) {
                const form = inputToDiscard.closest('.admin-profile-picture-form');
                const preview = form?.querySelector('.admin-profile-picture-preview');
                const submit = form?.querySelector('[data-profile-picture-submit]');
                const editButton = form?.querySelector('[data-profile-picture-edit]');
                inputToDiscard.value = '';
                if (preview instanceof HTMLElement && preview.dataset.originalMarkup !== undefined) {
                    preview.innerHTML = preview.dataset.originalMarkup;
                }
                if (submit instanceof HTMLButtonElement) submit.hidden = true;
                if (editButton instanceof HTMLButtonElement) editButton.disabled = false;
                if (form) {
                    const previewObjectUrl = previewObjectUrls.get(form);
                    if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
                    previewObjectUrls.delete(form);
                }
            }
        }

        if (dialog.open) dialog.close();
    };

    const openCropError = (input, message) => {
        activeInput = input;
        sourceImage = null;
        loadingMessage.hidden = true;
        zoomSlider.disabled = true;
        applyButton.disabled = true;
        showError(message);
        if (!dialog.open) dialog.showModal();
    };

    const openCrop = async (input, imageSource) => {
        activeInput = input;
        sourceImage = null;
        zoom = 1;
        offsetX = 0;
        offsetY = 0;
        zoomSlider.value = '1';
        zoomSlider.disabled = true;
        applyButton.disabled = true;
        loadingMessage.hidden = false;
        errorMessage.hidden = true;
        errorMessage.textContent = '';
        if (!dialog.open) dialog.showModal();

        try {
            let imageBlob = imageSource;
            if (typeof imageSource === 'string') {
                const response = await fetch(imageSource, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('The current profile picture could not be loaded.');
                imageBlob = await response.blob();
            }

            const decodedImage = await createImageBitmap(imageBlob);
            if (!dialog.open || activeInput !== input) {
                if (typeof decodedImage.close === 'function') decodedImage.close();
                return;
            }
            if (
                decodedImage.width < 1
                || decodedImage.height < 1
                || decodedImage.width > 5000
                || decodedImage.height > 5000
            ) {
                if (typeof decodedImage.close === 'function') decodedImage.close();
                throw new Error('Choose an image no larger than 5000 × 5000 pixels.');
            }

            sourceImage = decodedImage;
            loadingMessage.hidden = true;
            zoomSlider.disabled = false;
            applyButton.disabled = false;
            renderCrop();
        } catch (error) {
            if (!dialog.open || activeInput !== input) return;
            loadingMessage.hidden = true;
            showError(error instanceof Error ? error.message : 'The image could not be opened. Choose another photo.');
        }
    };

    document.querySelectorAll('[data-profile-picture-input]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        const form = input.closest('.admin-profile-picture-form');
        const preview = form?.querySelector('.admin-profile-picture-preview');
        const submit = form?.querySelector('[data-profile-picture-submit]');
        const editButton = form?.querySelector('[data-profile-picture-edit]');
        if (!(preview instanceof HTMLElement)) return;
        preview.dataset.originalMarkup = preview.innerHTML;

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                openCropError(input, 'Choose a JPG, PNG, or WebP image.');
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                openCropError(input, 'The profile picture must be no larger than 2 MB.');
                return;
            }
            openCrop(input, file);
        });

        if (editButton instanceof HTMLButtonElement) {
            editButton.addEventListener('click', () => {
                const image = preview.querySelector('img');
                if (image instanceof HTMLImageElement && image.src) openCrop(input, image.src);
            });
        }
    });

    zoomSlider.addEventListener('input', () => {
        zoom = Number(zoomSlider.value);
        renderCrop();
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (!sourceImage) return;
        dragPosition = { x: event.clientX, y: event.clientY };
        canvas.setPointerCapture(event.pointerId);
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!dragPosition || !sourceImage) return;
        const bounds = canvas.getBoundingClientRect();
        const scaleX = canvas.width / bounds.width;
        const scaleY = canvas.height / bounds.height;
        offsetX += (event.clientX - dragPosition.x) * scaleX;
        offsetY += (event.clientY - dragPosition.y) * scaleY;
        dragPosition = { x: event.clientX, y: event.clientY };
        renderCrop();
    });

    const stopDragging = () => { dragPosition = null; };
    canvas.addEventListener('pointerup', stopDragging);
    canvas.addEventListener('pointercancel', stopDragging);
    canvas.addEventListener('keydown', (event) => {
        if (!sourceImage || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        const step = event.shiftKey ? 20 : 5;
        if (event.key === 'ArrowUp') offsetY -= step;
        if (event.key === 'ArrowDown') offsetY += step;
        if (event.key === 'ArrowLeft') offsetX -= step;
        if (event.key === 'ArrowRight') offsetX += step;
        renderCrop();
    });

    applyButton.addEventListener('click', () => {
        if (!activeInput || !sourceImage) return;
        applyButton.disabled = true;
        canvas.toBlob((blob) => {
            if (!blob) {
                applyButton.disabled = false;
                showError('The cropped image could not be created. Please try again.');
                return;
            }

            try {
                const croppedFile = new File([blob], 'profile-picture.jpg', {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                activeInput.files = transfer.files;

                const form = activeInput.closest('.admin-profile-picture-form');
                const preview = form?.querySelector('.admin-profile-picture-preview');
                const submit = form?.querySelector('[data-profile-picture-submit]');
                if (!(preview instanceof HTMLElement) || !(submit instanceof HTMLButtonElement)) {
                    throw new Error('The profile picture preview could not be updated.');
                }

                const previousPreviewUrl = previewObjectUrls.get(form);
                if (previousPreviewUrl) URL.revokeObjectURL(previousPreviewUrl);
                const previewObjectUrl = URL.createObjectURL(croppedFile);
                previewObjectUrls.set(form, previewObjectUrl);
                preview.querySelector('img')?.remove();
                preview.querySelectorAll(':scope > :not(.admin-profile-picture-picker-icon)').forEach((node) => node.remove());
                const image = document.createElement('img');
                image.src = previewObjectUrl;
                image.alt = 'Cropped profile picture preview';
                preview.prepend(image);
                submit.hidden = false;
                closeCrop(false);
            } catch (error) {
                applyButton.disabled = false;
                showError(error instanceof Error ? error.message : 'The cropped image could not be selected. Please try again.');
            }
        }, 'image/jpeg', 0.9);
    });

    dialog.querySelectorAll('[data-profile-crop-cancel]').forEach((button) => {
        button.addEventListener('click', () => closeCrop(true));
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeCrop(true);
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeCrop(true);
    });
})();
