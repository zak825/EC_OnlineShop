var editForm = document.querySelector('.product-edit__form');
var editImages = document.getElementById('images');
var editPreview = document.getElementById('edit_image_preview');
var editPreviewLabel = document.getElementById('edit_image_preview_label');
var editImageError = document.getElementById('edit_image_error');
var editImageFileCount = document.getElementById('edit_image_file_count');
var editDescription = document.getElementById('description');
var editDescriptionCounter = document.getElementById('edit_description_counter');
var editImageMax = Number(editImages.dataset.imageMax);
var editImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
var editPicked = new DataTransfer();
var editPreviewUrls = [];
var editDragIndex = null;
var editRemoved = {};

function updateEditDescriptionCounter() {
  var length = Array.from(editDescription.value).length;
  editDescriptionCounter.textContent = length + ' / ' + editDescription.maxLength + '文字';
  editDescriptionCounter.classList.toggle('is-limit', length >= editDescription.maxLength);
}

editDescription.addEventListener('input', updateEditDescriptionCounter);
updateEditDescriptionCounter();

function updateEditImageFileCount() {
  editImageFileCount.textContent = editImages.files.length > 0
    ? editImages.files.length + 'ファイル'
    : '';
}

function setEditImageError(message) {
  editImageError.textContent = message;
  editImages.classList.toggle('is-invalid', message !== '');
}

function editCells() {
  return Array.from(editPreview.querySelectorAll('.image-list__cell'));
}

function editImageCount() {
  return editCells().length;
}

function isSameFile(left, right) {
  return left.name === right.name && left.size === right.size
    && left.lastModified === right.lastModified;
}

function clearEditDragClasses() {
  editPreview.querySelectorAll('.is-dragging, .is-dragover').forEach(function(cell) {
    cell.classList.remove('is-dragging', 'is-dragover');
  });
}

function syncEditFiles() {
  editImages.files = editPicked.files;
}

function renderEditPreview() {
  editPreviewUrls.forEach(function(url) { URL.revokeObjectURL(url); });
  editPreviewUrls = [];
  editCells().forEach(function(cell) { cell.remove(); });

  editItems.forEach(function(item, index) {
    var cell = document.createElement('div');
    cell.className = 'image-list__cell';
    cell.draggable = true;
    cell.dataset.index = index;
    if (item.existing) {
      cell.dataset.existing = item.existing;
    }

    var img = document.createElement('img');
    img.className = 'image-list__item';
    img.width = 100;
    img.height = 100;
    img.alt = '商品画像';
    if (item.file) {
      var url = URL.createObjectURL(item.file);
      editPreviewUrls.push(url);
      img.src = url;
    } else {
      img.src = item.url;
    }

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'image-list__remove';
    remove.setAttribute('aria-label', 'この画像を削除');
    remove.innerHTML = '&times;';
    cell.appendChild(img);
    cell.appendChild(remove);
    editPreview.appendChild(cell);
  });
  editPreviewLabel.hidden = editItems.length === 0;
  updateEditImageArrows();
}

function updateEditImageArrows() {
  var previous = document.getElementById('edit_image_prev');
  var next = document.getElementById('edit_image_next');
  var items = editPreview.children;
  if (items.length === 0) {
    previous.hidden = true;
    next.hidden = true;
    return;
  }

  var box = editPreview.getBoundingClientRect();
  var left = Infinity;
  var right = -Infinity;
  for (var i = 0; i < items.length; i++) {
    var rect = items[i].getBoundingClientRect();
    left = Math.min(left, rect.left);
    right = Math.max(right, rect.right);
  }
  previous.hidden = left >= box.left - 1;
  next.hidden = right <= box.right + 1;
}

function moveEditImages(from, to) {
  var item = editItems.splice(from, 1)[0];
  editItems.splice(to, 0, item);
  renderEditPreview();
}

function indexFromEditX(x, from) {
  var count = 0;
  editCells().forEach(function(cell, index) {
    if (index !== from && cell.getBoundingClientRect().left
      + cell.getBoundingClientRect().width / 2 > x) {
      count++;
    }
  });
  return count;
}

var editItems = editCells().map(function(cell) {
  return {
    existing: cell.dataset.existing,
    url: cell.querySelector('img').src,
    file: null
  };
});

editPreview.addEventListener('click', function(event) {
  var button = event.target.closest('.image-list__remove');
  if (!button) return;
  var cell = button.parentNode;
  var index = Number(cell.dataset.index);
  var item = editItems[index];
  if (item.existing) editRemoved[item.existing] = true;
  editItems.splice(index, 1);
  editPicked = new DataTransfer();
  editItems.filter(function(current) { return current.file; }).forEach(function(current) {
    editPicked.items.add(current.file);
  });
  syncEditFiles();
  updateEditImageFileCount();
  renderEditPreview();
});

editPreview.addEventListener('dragstart', function(event) {
  var cell = event.target.closest('.image-list__cell');
  if (!cell) return;
  editDragIndex = Number(cell.dataset.index);
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(editDragIndex));
  cell.classList.add('is-dragging');
});

editPreview.addEventListener('dragover', function(event) {
  if (editDragIndex === null) return;
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';
  editPreview.querySelectorAll('.is-dragover').forEach(function(cell) {
    cell.classList.remove('is-dragover');
  });
  var cell = event.target.closest('.image-list__cell');
  if (cell) cell.classList.add('is-dragover');
});

editPreview.addEventListener('drop', function(event) {
  if (editDragIndex === null) return;
  event.preventDefault();
  var from = editDragIndex;
  editDragIndex = null;
  var cell = event.target.closest('.image-list__cell');
  var to = cell ? Number(cell.dataset.index) : indexFromEditX(event.clientX, from);
  if (from !== to) moveEditImages(from, to);
  else clearEditDragClasses();
});

editPreview.addEventListener('dragend', function() {
  editDragIndex = null;
  clearEditDragClasses();
});

editImages.addEventListener('change', function() {
  var files = Array.from(editImages.files);
  var beforeCount = editItems.length;
  var invalid = false;
  files.forEach(function(file) {
    if (editImageTypes.indexOf(file.type) === -1) invalid = true;
    if (file.size > 2 * 1024 * 1024) invalid = true;
  });
  if (invalid) {
    setEditImageError('JPEG・PNG・GIF・WebP の画像、1枚2MBまでを選択してください');
  }

  var available = editImageMax - editItems.length;
  files.forEach(function(file) {
    if (available <= 0) return;
    if (editImageTypes.indexOf(file.type) === -1 || file.size > 2 * 1024 * 1024) return;
    if (editItems.some(function(item) { return item.file && isSameFile(item.file, file); })) return;
    editItems.push({ file: file, existing: null, url: null });
    editPicked.items.add(file);
    available--;
  });
  if (files.length > editImageMax - beforeCount) {
    setEditImageError('画像は' + editImageMax + '枚までです');
  } else if (!invalid) {
    setEditImageError('');
  }
  syncEditFiles();
  updateEditImageFileCount();
  renderEditPreview();
});

editPreview.addEventListener('scroll', updateEditImageArrows);
document.getElementById('edit_image_prev').addEventListener('click', function() {
  editPreview.scrollBy({ left: -editPreview.clientWidth * 0.8, behavior: 'smooth' });
});
document.getElementById('edit_image_next').addEventListener('click', function() {
  editPreview.scrollBy({ left: editPreview.clientWidth * 0.8, behavior: 'smooth' });
});
window.addEventListener('resize', updateEditImageArrows);

function showNewestEditImages() {
  editPreview.scrollLeft = -editPreview.scrollWidth;
  updateEditImageArrows();
}

new MutationObserver(function() {
  showNewestEditImages();
  setTimeout(updateEditImageArrows, 300);
}).observe(editPreview, { childList: true });

editForm.addEventListener('submit', function() {
  editPicked = new DataTransfer();
  var orderInputs = editForm.querySelectorAll('[data-image-order]');
  orderInputs.forEach(function(input) { input.remove(); });
  var newIndex = 0;
  editItems.forEach(function(item) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'image_order[]';
    input.dataset.imageOrder = 'true';
    input.value = item.existing || 'new:' + newIndex;
    editForm.appendChild(input);
    if (item.file) {
      editPicked.items.add(item.file);
      newIndex++;
    }
  });
  syncEditFiles();
  updateEditImageFileCount();
  Object.keys(editRemoved).forEach(function(fileName) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'remove_images[]';
    input.value = fileName;
    editForm.appendChild(input);
  });
});

renderEditPreview();
updateEditImageFileCount();
showNewestEditImages();
