Mike of All Trades Work Tracker V8.11

This update adds the final Work Tracker activity controls:

- Finish activity can attach photos.
- Finish activity has Save only and Save + SMS customer buttons.
- Finish current task + start next task closes the current timer first, then starts the selected next task.
- Waiting for drying / curing / setting can attach stage photos and can be saved with or without SMS.
- Waiting for drying / curing / setting can also start another selected task immediately, after the drying task's timer is stopped.
- Task updates can attach progress photos; completed task updates save those photos as after photos.

Database:

The supplied current SQL export already includes the required columns and tables.
For older installs, run tools/migration_v8_11_task_progress_photos.sql if work_task_photos.photo_type does not already allow progress.

Install:

Copy the files in this package over the existing project files, preserving the same paths.
Back up the current project and database before replacing files.
