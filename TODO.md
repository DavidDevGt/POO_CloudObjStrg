function Show-Tree {
    param (
        [string]$Path = ".",
        [string]$Indent = ""
    )

    Get-ChildItem -LiteralPath $Path | ForEach-Object {
        if ($_.Name -ne "node_modules" -and $_.Name -ne "vendor") {
            Write-Host "$Indent$($_.Name)"
            if ($_.PSIsContainer) {
                Show-Tree $_.FullName "$Indent    "
            }
        }
    }
}

Show-Tree -LiteralPath 'C:\xampp\htdocs\POO_CloudObjStrg\'