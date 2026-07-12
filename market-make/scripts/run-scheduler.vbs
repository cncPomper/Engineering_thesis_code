' Runs the Laravel scheduler without flashing a console window.
' Registered in Windows Task Scheduler (task name: "market-make scheduler")
' to execute every minute; Laravel decides which jobs are actually due.
Set shell = CreateObject("WScript.Shell")
shell.Run """C:\xampp\php\php.exe"" ""C:\Users\Piotr\Engineering_thesis_code\market-make\artisan"" schedule:run", 0, False
