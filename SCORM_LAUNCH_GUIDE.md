# 📋 SCORM Package Launch Guide

Complete step-by-step documentation to launch the game from extracted SCORM package.

---

## 📦 Step 1: Extract SCORM Package

1. **Download** the SCORM package (`.zip` file) from the dashboard export
2. **Extract** the zip file on your Desktop
3. **Rename** the folder (optional, e.g., `Equations`, `Algebra`, etc.)

### Expected Folder Structure
```
Desktop/
├── Equations/  (or your custom name)
│   ├── data/
│   │   └── levels_data.json
│   ├── index.html
│   ├── index.js
│   ├── scorm.js
│   ├── imsmanifest.xml
│   └── ... (other game files)
```

---


### Example Path
```
C:\Users\Usuario\Desktop\2PI_Dashboard_Game-main - Copy\Equations
```

---

## 📂 Step 2: Copy Game Data to Backend Storage

This step imports the game levels data into the backend for access.

### Open PowerShell
1. Open **PowerShell**
2. Run this command (replace `NOM_DOSSIER` with your folder name if different):

```powershell
copy "%USERPROFILE%\Downloads\NOM_DOSSIER\data\levels_data.json" "C:\Users\hp\Desktop\2pi-Dashboard-version_finale\BackEnd\storage\app\games\game_2\data\levels_data.json" /Y
```

### What This Does
- ✅ Copies `levels_data.json` from the SCORM folder
- ✅ Pastes it to the backend storage directory


---

## 🚀 Step 3: Start Python HTTP Server

### Navigate to Game Directory
```powershell
cd "C:\Users\Usuario\Desktop\2PI_Dashboard_Game-main - Copy\BackEnd\storage\app\games\game_2"
```

### Start Server
```powershell
python -m http.server 8080
```

### Expected Output
```
Serving HTTP on 0.0.0.0 port 8080 (http://0.0.0.0:8080/) ...
```

> **Note:** This requires Python to be installed. If not installed, download from https://www.python.org

---

## 🌐 Step 4: Open Game in Browser

1. **Open** your web browser (Chrome, Firefox, Edge, etc.)
2. **Navigate** to: `http://localhost:8080`
3. **Wait** for the page to load

### Expected Result
- ✅ Game interface appears
- ✅ Questions display from your video/PDF sources
- ✅ Game is playable (boxes with answers visible)
- ⚠️ SCORM debug warning appears (normal - ignore it)

---

## 🎮 Step 5: Test & Verify

### Checklist
- [ ] Game loads without errors
- [ ] Questions are visible
- [ ] Questions are relevant to your content
- [ ] Game is interactive (can click answers)
- [ ] Multiple levels appear (if configured)

### Known Messages
```
[SCORM DEBUG] scorm_initialize: API not found.
window.API = undefined
window.parent.API = undefined
```
**This is NORMAL.** It only matters if you're uploading to an LMS (Moodle, Canvas, etc.).

---

## 📊 File Locations Reference

| Item | Location |
|------|----------|
|
| 
| Backend Storage | `Desktop\2PI_Dashboard_Game-main - Copy\BackEnd\storage\app\games\game_2\data\` |
| Game Server | `http://localhost:8080` |

---

## 🔧 Troubleshooting

### Problem: "Python not found"
**Solution:** Install Python from https://www.python.org

### Problem: "File not found" error
**Solution:** Verify the folder name matches exactly in the copy command

### Problem: "Port 8080 already in use"
**Solution:** 
```powershell
# Kill the process using port 8080
netstat -ano | findstr :8080
taskkill /PID <PID_NUMBER> /F

# Then restart the server
python -m http.server 8080
```

### Problem: Blank page loads
**Solution:** 
- Refresh browser (Ctrl+F5)
- Check browser console (F12) for errors
- Verify `levels_data.json` was copied correctly

---

## 📝 Notes

- **Server Duration:** The Python server runs until you press `Ctrl+C` in the terminal
- **Multiple Tests:** You can test multiple SCORM packages by repeating steps 2-3
- **LMS Integration:** To upload to Moodle/Canvas, use the original `.zip` file (don't extract)

---

## ✅ Quick Summary

| Step | Command | Purpose |
|------|---------|---------|
| 1 | Extract `.zip` | Get SCORM files |
| 2 |  Copy `levels_data.json` | Import data to backend |
| 3 | `python -m http.server 8080` | Start game server |
| 4 | Open `http://localhost:8080` | Play game |

---

**Last Updated:** March 20, 2026
**Status:** ✅ Working & Verified
