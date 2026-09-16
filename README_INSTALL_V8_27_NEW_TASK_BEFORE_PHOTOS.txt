V8.27 - BEFORE PHOTOS WHILE ADDING A TASK
==========================================

The Add another task form now accepts up to 20 optional before photos.

The task is created first so it has a valid task ID, then each selected image is
stored through the existing protected task-photo pipeline as a BEFORE photo.
This also creates its thumbnails, social variants and social draft records using
the same processing as the existing task photo uploader.

The task remains safely saved if an image fails. The manage-job page reports how
many before photos were attached, or warns that the photos need to be added again.
