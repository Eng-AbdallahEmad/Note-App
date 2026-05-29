<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notes App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .note-card { transition: transform .15s; }
        .note-card:hover { transform: translateY(-2px); }
        .pinned-badge { font-size: .7rem; }
        #formCard { position: sticky; top: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold fs-4"><i class="fa-solid fa-note-sticky me-2"></i>Notes App</span>
    </div>
</nav>

<div class="container">
    <div class="row g-4">

        {{-- Form --}}
        <div class="col-lg-4">
            <div class="card shadow-sm" id="formCard">
                <div class="card-header bg-primary text-white fw-semibold" id="formTitle">
                    إضافة ملاحظة جديدة
                </div>
                <div class="card-body">
                    <input type="hidden" id="noteId">
                    <div class="mb-3">
                        <label class="form-label fw-medium">العنوان</label>
                        <input type="text" class="form-control" id="noteTitle" placeholder="عنوان الملاحظة">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">المحتوى</label>
                        <textarea class="form-control" id="noteContent" rows="4" placeholder="اكتب هنا..."></textarea>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="notePinned">
                        <label class="form-check-label" for="notePinned">تثبيت الملاحظة</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary grow" id="saveBtn" onclick="saveNote()">
                            <i class="fa-solid fa-floppy-disk me-1"></i> حفظ
                        </button>
                        <button class="btn btn-outline-secondary" id="cancelBtn" onclick="resetForm()" style="display:none">
                            إلغاء
                        </button>
                    </div>
                    <div id="formAlert" class="mt-3"></div>
                </div>
            </div>
        </div>

        {{-- Notes List --}}
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-secondary">الملاحظات</h5>
                <span class="badge bg-primary rounded-pill" id="notesCount">0</span>
            </div>

            <div id="loadingSpinner" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <div id="emptyState" class="text-center py-5 text-muted" style="display:none">
                <i class="fa-solid fa-inbox" style="font-size:3rem"></i>
                <p class="mt-2">لا توجد ملاحظات بعد، أضف أولى ملاحظاتك!</p>
            </div>

            <div id="notesList" class="row g-3"></div>
        </div>

    </div>
</div>

{{-- Delete Confirm Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">هل أنت متأكد من حذف هذه الملاحظة؟</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">حذف</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/api/v1/notes';
let deleteModal, deleteTargetId;

document.addEventListener('DOMContentLoaded', () => {
    deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);
    loadNotes();
});

async function loadNotes() {
    try {
        const res = await fetch(API);
        const notes = await res.json();
        renderNotes(notes);
    } catch {
        showAlert('formAlert', 'خطأ في تحميل الملاحظات', 'danger');
    }
}

function renderNotes(notes) {
    const list = document.getElementById('notesList');
    const spinner = document.getElementById('loadingSpinner');
    const empty = document.getElementById('emptyState');

    spinner.style.display = 'none';
    document.getElementById('notesCount').textContent = notes.length;

    if (!notes.length) {
        list.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';

    const pinned = notes.filter(n => n.is_pinned);
    const unpinned = notes.filter(n => !n.is_pinned);
    const sorted = [...pinned, ...unpinned];

    list.innerHTML = sorted.map(note => `
        <div class="col-12 col-md-6">
            <div class="card note-card h-100 shadow-sm ${note.is_pinned ? 'border-warning' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="card-title fw-bold mb-0">${escHtml(note.title)}</h6>
                        ${note.is_pinned ? '<span class="badge bg-warning text-dark pinned-badge"><i class="fa-solid fa-thumbtack me-1"></i>مثبت</span>' : ''}
                    </div>
                    <p class="card-text text-muted small mt-2 mb-3" style="white-space:pre-wrap">${escHtml(note.content)}</p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm flex-grow-1" onclick="editNote(${note.id})">
                            <i class="fa-solid fa-pen me-1"></i> تعديل
                        </button>
                        <button class="btn btn-outline-danger btn-sm flex-grow-1" onclick="openDeleteModal(${note.id})">
                            <i class="fa-solid fa-trash me-1"></i> حذف
                        </button>
                    </div>
                </div>
                <div class="card-footer text-muted" style="font-size:.75rem">
                    ${new Date(note.created_at).toLocaleDateString('ar-EG')}
                </div>
            </div>
        </div>
    `).join('');
}

async function saveNote() {
    const id      = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value.trim();
    const pinned  = document.getElementById('notePinned').checked;

    if (!title || !content) {
        showAlert('formAlert', 'الرجاء ملء العنوان والمحتوى', 'warning');
        return;
    }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> جارٍ الحفظ...';

    const body = JSON.stringify({ title, content, is_pinned: pinned });
    const url  = id ? `${API}/${id}` : API;
    const method = id ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body
        });

        if (!res.ok) {
            const err = await res.json();
            const msg = err.message || 'حدث خطأ';
            showAlert('formAlert', msg, 'danger');
            return;
        }

        showAlert('formAlert', id ? 'تم التحديث بنجاح' : 'تم الحفظ بنجاح', 'success');
        resetForm();
        loadNotes();
    } catch {
        showAlert('formAlert', 'تعذر الاتصال بالخادم', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> حفظ';
    }
}

async function editNote(id) {
    try {
        const res  = await fetch(`${API}/${id}`);
        const note = await res.json();

        document.getElementById('noteId').value       = note.id;
        document.getElementById('noteTitle').value    = note.title;
        document.getElementById('noteContent').value  = note.content;
        document.getElementById('notePinned').checked = note.is_pinned;

        document.getElementById('formTitle').textContent = 'تعديل الملاحظة';
        document.getElementById('cancelBtn').style.display = '';
        document.getElementById('formCard').scrollIntoView({ behavior: 'smooth' });
    } catch {
        showAlert('formAlert', 'تعذر تحميل بيانات الملاحظة', 'danger');
    }
}

function openDeleteModal(id) {
    deleteTargetId = id;
    deleteModal.show();
}

async function confirmDelete() {
    deleteModal.hide();
    try {
        await fetch(`${API}/${deleteTargetId}`, { method: 'DELETE' });
        loadNotes();
    } catch {
        showAlert('formAlert', 'تعذر حذف الملاحظة', 'danger');
    }
}

function resetForm() {
    document.getElementById('noteId').value       = '';
    document.getElementById('noteTitle').value    = '';
    document.getElementById('noteContent').value  = '';
    document.getElementById('notePinned').checked = false;
    document.getElementById('formTitle').textContent = 'إضافة ملاحظة جديدة';
    document.getElementById('cancelBtn').style.display = 'none';
    document.getElementById('formAlert').innerHTML = '';
}

function showAlert(elId, msg, type) {
    const el = document.getElementById(elId);
    el.innerHTML = `<div class="alert alert-${type} alert-dismissible py-2 mb-0" role="alert">
        ${msg}
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
    </div>`;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
</body>
</html>
