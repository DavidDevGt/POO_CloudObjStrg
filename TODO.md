
function Show-Tree {
    param(
        [string]$Path,
        [string]$ExcludeDir
    )

    $indent = "    "
    $lastItemIndent = "    "
    $branch = "│   "
    $tee = "├── "
    $last = "└── "

    $stack = New-Object System.Collections.Stack

    Get-ChildItem -Path $Path -Recurse | Where-Object { $_.FullName -notmatch "\\$ExcludeDir\\" } | ForEach-Object {
        $currentDepth = ($_.FullName -replace [regex]::Escape($Path), '').Split([IO.Path]::DirectorySeparatorChar).Count - 1
        $isLastItem = -not (Get-ChildItem -Path $_.PSParentPath | Where-Object { $_.FullName -gt $_.FullName -and $_.FullName -notmatch "\\$ExcludeDir\\" })

        while ($stack.Count -gt 0 -and $stack.Peek().Depth -ge $currentDepth) {
            $stack.Pop()
        }

        $indentation = ""
        $stack | ForEach-Object { $indentation += $_.Indent }
        
        if ($isLastItem) {
            $indentation += $last
            $nextIndent = $lastItemIndent
        } else {
            $indentation += $tee
            $nextIndent = $branch
        }

        $stack.Push((New-Object PSObject -Property @{ Depth = $currentDepth; Indent = $nextIndent }))
        $indentation += $_.Name
        $indentation
    }
}

# Ejemplo de uso:
Show-Tree -Path "C:\xampp\htdocs\POO_CloudObjStrg" -ExcludeDir "vendor"
