<footer class="footer-section py-5 mt-5 border-top border-secondary bg-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-4 mb-md-0">
                <h4 class="mb-1" style="color: #0dcaf0; font-weight: 800; letter-spacing: 0.5px;">
                    MIKE OF ALL TRADES
                </h4>

                <p class="mb-2 text-secondary small uppercase tracking-wider">
                    ABN: 92 707 598 477
                </p>

                <p class="mb-0 text-muted small">
                    &copy; 2026 | Victoria, Australia
                    <span class="mx-2">|</span>
                    <a href="<?= $baseUrl ?>terms.php"
                       class="text-secondary text-decoration-none"
                       title="Mike Of All Trades Terms & Conditions">
                        Terms &amp; Conditions
                    </a>
                </p>
            </div>

            <div class="col-md-6 text-center text-md-end">
                <div class="mb-3">
                    <a href="javascript:void(0);"
                       class="btn btn-outline-info btn-sm px-4 rounded-pill fw-bold"
                       data-bs-toggle="modal"
                       data-bs-target="#contactOptionsModal">
                        <i class="bi bi-chat-dots me-2"></i>Contact Us / Get a Quote
                    </a>
                </div>

                <div class="fs-4">
                    <a href="https://www.instagram.com/mikeofall_trades_/"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-secondary hover-info me-3 transition-all"
                       aria-label="Mike of All Trades on Instagram"
                       title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>

                    <a href="https://www.facebook.com/mikeofalltradesmelbourne"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-secondary hover-info me-3 transition-all"
                       aria-label="Mike of All Trades on Facebook"
                       title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<div class="modal fade" id="contactOptionsModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="quoteModalLabel">
                        How can we help?
                    </h5>
                    <div class="small text-white-50">
                        Choose the option that suits you best.
                    </div>
                </div>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">

                <div id="contactChoicePanel">
                    <div class="row g-3">

                        <div class="col-12">
                            <a href="<?= $baseUrl ?>ai_helper.php?new=1&intent=quote&source=footer-modal"
                               class="text-decoration-none">
                                <div class="border rounded-4 p-4 h-100 bg-light position-relative contact-choice-card">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="fs-2">✨</div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                <h5 class="mb-0 text-dark fw-bold">
                                                    Get a quick AI quote
                                                </h5>
                                                <span class="badge text-bg-info">
                                                    Fastest
                                                </span>
                                            </div>

                                            <p class="mb-0 text-secondary">
                                                Describe the job, upload photos or PDF plans, and let Mike's AI assistant
                                                help work out an indicative quote right now.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-6">
                            <button type="button"
                                    class="btn w-100 text-start p-0 border-0 bg-transparent"
                                    onclick="showFooterContactForm('quote')">
                                <div class="border rounded-4 p-4 h-100 bg-white contact-choice-card">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="fs-2">👤</div>
                                        <div>
                                            <h5 class="mb-1 text-dark fw-bold">
                                                Ask Mike for a quote
                                            </h5>

                                            <p class="mb-0 text-secondary">
                                                Send Mike the job details and he can review them and get back to you.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div class="col-md-6">
                            <button type="button"
                                    class="btn w-100 text-start p-0 border-0 bg-transparent"
                                    onclick="showFooterContactForm('contact')">
                                <div class="border rounded-4 p-4 h-100 bg-white contact-choice-card">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="fs-2">💬</div>
                                        <div>
                                            <h5 class="mb-1 text-dark fw-bold">
                                                Contact us
                                            </h5>

                                            <p class="mb-0 text-secondary">
                                                Send a general question or enquiry and Mike will get back to you.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>

                    </div>
                </div>

                <div id="contactFormPanel" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" id="footerFormHeading">
                                Contact Mike
                            </h5>

                            <p class="small text-secondary mb-0" id="footerFormIntro">
                                Tell Mike what you need and he can get back to you.
                            </p>
                        </div>

                        <button type="button"
                                class="btn btn-outline-secondary btn-sm rounded-pill px-3"
                                onclick="showFooterChoices()">
                            ← Back
                        </button>
                    </div>

                    <form action="<?= $baseUrl ?>process_quote.php" method="POST" id="footerContactForm" enctype="multipart/form-data">

                        <input type="hidden"
                               name="footer_enquiry_type"
                               id="footerEnquiryType"
                               value="">

                        <input type="text"
                               name="website_url"
                               style="display:none !important"
                               tabindex="-1"
                               autocomplete="off">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">
                                Name
                            </label>

                            <input type="text"
                                   name="name"
                                   class="form-control rounded-3"
                                   placeholder="Enter your name"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">
                                Email
                            </label>

                            <input type="email"
                                   name="email"
                                   class="form-control rounded-3"
                                   placeholder="name@example.com"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">
                                Project Type
                            </label>

                            <select name="service"
                                    id="footerServiceSelect"
                                    class="form-select rounded-3">
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Creative/Media">Creative / Media Production</option>
                                <option value="Technical/IT">Technical / IT Work</option>
                                <option value="Trades/Handyman">Trades / Handyman Services</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">
                                What is 5 + 5? (Spam Check)
                            </label>

                            <input type="number"
                                   name="math_answer"
                                   class="form-control rounded-3"
                                   placeholder="Your answer"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase"
                                   id="footerMessageLabel">
                                How can I help?
                            </label>

                            <textarea name="message"
                                      id="footerMessage"
                                      class="form-control rounded-3"
                                      rows="5"
                                      placeholder="Describe your project details..."
                                      required></textarea>
                        </div>

                        <div class="mb-4" id="footerAttachmentBlock" style="display:none;">
                            <label class="form-label small fw-bold text-secondary text-uppercase">
                                Photos / plans / PDFs
                            </label>

                            <div class="border rounded-4 p-3 bg-light">
                                <label for="footerAttachments"
                                       class="btn btn-outline-secondary rounded-pill fw-bold mb-2">
                                    <i class="bi bi-paperclip me-2"></i>Add photos / files / plans
                                </label>

                                <input type="file"
                                       id="footerAttachments"
                                       name="attachments[]"
                                       accept="image/jpeg,image/png,image/webp,application/pdf,.pdf"
                                       multiple
                                       hidden>

                                <div class="small text-secondary">
                                    Up to 10 JPG, PNG, WEBP or PDF files. Maximum 10 MB per file.
                                </div>

                                <div id="footerAttachmentPreview"
                                     class="d-flex flex-wrap gap-2 mt-3"></div>

                                <div id="footerAttachmentError"
                                     class="small text-danger mt-2"></div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit"
                                    class="btn btn-info text-white fw-bold py-3 rounded-pill shadow-sm"
                                    id="footerSubmitButton">
                                SEND REQUEST
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
    .contact-choice-card {
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }

    .contact-choice-card:hover {
        transform: translateY(-2px);
        border-color: #0dcaf0 !important;
        box-shadow: 0 .5rem 1.2rem rgba(0,0,0,.08);
    }

    .footer-upload-preview {
        width: 92px;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        padding: 6px;
        background: #fff;
        position: relative;
    }

    .footer-upload-preview img {
        width: 100%;
        height: 68px;
        object-fit: cover;
        border-radius: 8px;
        display: block;
    }

    .footer-upload-pdf {
        height: 68px;
        border-radius: 8px;
        background: #343a40;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }

    .footer-upload-name {
        font-size: 10px;
        color: #6c757d;
        margin-top: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .footer-upload-remove {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #fff;
        background: #dc3545;
        color: #fff;
        padding: 0;
        line-height: 19px;
        font-weight: 700;
    }

</style>

<script>
function showFooterContactForm(type) {
    const choices = document.getElementById('contactChoicePanel');
    const formPanel = document.getElementById('contactFormPanel');
    const enquiryType = document.getElementById('footerEnquiryType');
    const heading = document.getElementById('footerFormHeading');
    const intro = document.getElementById('footerFormIntro');
    const serviceSelect = document.getElementById('footerServiceSelect');
    const messageLabel = document.getElementById('footerMessageLabel');
    const message = document.getElementById('footerMessage');
    const submitButton = document.getElementById('footerSubmitButton');
    const attachmentBlock = document.getElementById('footerAttachmentBlock');

    enquiryType.value = type;

    if (type === 'quote') {
        heading.textContent = 'Ask Mike for a quote';
        intro.textContent = 'Send Mike the job details and he can review them and get back to you.';
        serviceSelect.value = 'Trades/Handyman';
        messageLabel.textContent = 'What would you like quoted?';
        message.placeholder = 'Describe the job, location, measurements, access, materials, urgency, or anything else that may help Mike quote it...';
        submitButton.textContent = 'SEND QUOTE REQUEST';
        if (attachmentBlock) {
            attachmentBlock.style.display = 'block';
        }
    } else {
        heading.textContent = 'Contact Mike';
        intro.textContent = 'Send a general question or enquiry and Mike will get back to you.';
        serviceSelect.value = 'General Inquiry';
        messageLabel.textContent = 'How can I help?';
        message.placeholder = 'Type your message here...';
        submitButton.textContent = 'SEND MESSAGE';
        if (attachmentBlock) {
            attachmentBlock.style.display = 'none';
        }
        clearFooterAttachments();
    }

    choices.style.display = 'none';
    formPanel.style.display = 'block';
}

function showFooterChoices() {
    const choices = document.getElementById('contactChoicePanel');
    const formPanel = document.getElementById('contactFormPanel');

    formPanel.style.display = 'none';
    choices.style.display = 'block';
    clearFooterAttachments();
}


let footerSelectedAttachments = [];

function clearFooterAttachments() {
    footerSelectedAttachments = [];

    const input = document.getElementById('footerAttachments');
    const preview = document.getElementById('footerAttachmentPreview');
    const error = document.getElementById('footerAttachmentError');

    if (input) input.value = '';
    if (preview) preview.innerHTML = '';
    if (error) error.textContent = '';
}

function renderFooterAttachmentPreview() {
    const preview = document.getElementById('footerAttachmentPreview');
    if (!preview) return;

    preview.innerHTML = '';

    footerSelectedAttachments.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'footer-upload-preview';

        let visual;

        if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
            visual = document.createElement('div');
            visual.className = 'footer-upload-pdf';
            visual.textContent = 'PDF';
        } else {
            visual = document.createElement('img');
            visual.alt = 'Selected attachment';

            const objectUrl = URL.createObjectURL(file);
            visual.src = objectUrl;
            visual.onload = function () {
                URL.revokeObjectURL(objectUrl);
            };
        }

        const name = document.createElement('div');
        name.className = 'footer-upload-name';
        name.textContent = file.name;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'footer-upload-remove';
        remove.setAttribute('aria-label', 'Remove attachment');
        remove.textContent = '×';
        remove.addEventListener('click', function () {
            footerSelectedAttachments.splice(index, 1);
            renderFooterAttachmentPreview();
        });

        item.appendChild(visual);
        item.appendChild(name);
        item.appendChild(remove);
        preview.appendChild(item);
    });
}

document.addEventListener('change', function (event) {
    if (event.target.id !== 'footerAttachments') {
        return;
    }

    const error = document.getElementById('footerAttachmentError');
    if (error) error.textContent = '';

    const incoming = Array.from(event.target.files || []);
    const allowed = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf'
    ];

    for (const file of incoming) {
        if (footerSelectedAttachments.length >= 10) {
            if (error) error.textContent = 'Maximum 10 attachments per request.';
            break;
        }

        if (!allowed.includes(file.type)) {
            if (error) error.textContent = 'Only JPG, PNG, WEBP and PDF files are allowed.';
            continue;
        }

        if (file.size > 10 * 1024 * 1024) {
            if (error) error.textContent = file.name + ' is larger than 10 MB.';
            continue;
        }

        const duplicate = footerSelectedAttachments.some(existing =>
            existing.name === file.name &&
            existing.size === file.size &&
            existing.lastModified === file.lastModified
        );

        if (!duplicate) {
            footerSelectedAttachments.push(file);
        }
    }

    event.target.value = '';
    renderFooterAttachmentPreview();
});

document.addEventListener('submit', function (event) {
    if (event.target.id !== 'footerContactForm') {
        return;
    }

    const enquiryType = document.getElementById('footerEnquiryType')?.value || '';

    if (enquiryType !== 'quote' || footerSelectedAttachments.length === 0) {
        return;
    }

    // The browser's FileList cannot be assigned directly, so use DataTransfer.
    const input = document.getElementById('footerAttachments');

    if (input && typeof DataTransfer !== 'undefined') {
        const dt = new DataTransfer();

        footerSelectedAttachments.forEach(file => {
            dt.items.add(file);
        });

        input.files = dt.files;
    }
});


document.addEventListener('DOMContentLoaded', function () {
const quoteModal = document.getElementById('contactOptionsModal');
    if (quoteModal) {
        quoteModal.addEventListener('hidden.bs.modal', function () {
            showFooterChoices();

            const form = document.getElementById('footerContactForm');
            if (form) {
                form.reset();
            }
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
