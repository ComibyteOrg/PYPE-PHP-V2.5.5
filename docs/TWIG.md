# Twig Templating Guide

## Overview

Pype PHP integrates [Twig](https://twig.symfony.com/) - a modern, secure templating engine for PHP. Twig separates your HTML from PHP logic, provides automatic XSS protection, and offers powerful template features like inheritance, filters, and functions.

---

## Setup

### Configure View Path

```php
// In routes/web.php
Route::setViewPath(__DIR__ . '/../Resources/views');
```

### Create Templates

Place Twig templates in `Resources/views/` with `.twig` extension:

```
Resources/views/
├── layout.twig
├── home.twig
├── about.twig
└── users/
    ├── index.twig
    └── show.twig
```

---

## Rendering Templates

### In Controllers

```php
// Render a Twig template
view('home', ['title' => 'Home Page']);

// With multiple variables
view('users.index', [
    'users' => $users,
    'title' => 'All Users'
]);

// Return as string
$html = view('email.welcome', ['name' => 'John'], true);
```

### Automatic Twig Detection

The `view()` function automatically detects Twig templates:

```php
// If Resources/views/home.twig exists, uses Twig
view('home', $data);

// If only Resources/views/home.php exists, uses PHP
view('home', $data);

// Explicit Twig with .twig extension
view('home.twig', $data);
```

---

## Template Inheritance

### Base Layout

```twig
{# Resources/views/layout.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My App{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <header>
        <nav>
            <a href="{{ url('/') }}">Home</a>
            <a href="{{ url('/about') }}">About</a>
            {% if check() %}
                <a href="{{ url('/dashboard') }}">Dashboard</a>
                <a href="{{ url('/logout') }}">Logout</a>
            {% else %}
                <a href="{{ url('/login') }}">Login</a>
            {% endif %}
        </nav>
    </header>

    <main>
        {% block content %}{% endblock %}
    </main>

    <footer>
        <p>&copy; {{ now()|date('Y') }} My App</p>
    </footer>
</body>
</html>
```

### Child Template

```twig
{# Resources/views/home.twig #}
{% extends "layout.twig" %}

{% block title %}Home - My App{% endblock %}

{% block content %}
    <h1>Welcome to My App</h1>
    <p>This is the home page.</p>
{% endblock %}
```

---

## Variables

### Output Variables

```twig
{{ name }}
{{ user.email }}
{{ post.title }}
```

### Default Values

```twig
{{ name|default('Guest') }}
{{ user.bio|default('No bio yet') }}
```

### Raw Output (No Escaping)

```twig
{{ html_content|raw }}
```

**Warning:** Only use `|raw` with trusted content to avoid XSS.

---

## Control Structures

### If Statements

```twig
{% if user %}
    <p>Welcome, {{ user.name }}!</p>
{% elseif guest %}
    <p>Welcome, Guest!</p>
{% else %}
    <p>Please log in.</p>
{% endif %}
```

### Comparisons

```twig
{% if age >= 18 %}
    <p>Adult</p>
{% endif %}

{% if status == 'active' %}
    <p>Active</p>
{% endif %}

{% if role != 'admin' %}
    <p>Not admin</p>
{% endif %}
```

### Logical Operators

```twig
{% if user and user.active %}
    <p>Active user</p>
{% endif %}

{% if role == 'admin' or role == 'moderator' %}
    <p>Staff member</p>
{% endif %}
```

### For Loops

```twig
<ul>
    {% for user in users %}
        <li>{{ user.name }} - {{ user.email }}</li>
    {% endfor %}
</ul>
```

### Loop Variables

```twig
{% for user in users %}
    <div class="user {% if loop.first %}first{% endif %} {% if loop.last %}last{% endif %}">
        <span>#{{ loop.index }}</span>
        <span>{{ user.name }}</span>
        {% if loop.index is even %}
            <span>Even row</span>
        {% endif %}
    </div>
{% else %}
    <p>No users found.</p>
{% endfor %}
```

| Variable | Description |
|----------|-------------|
| `loop.index` | Current iteration (1-indexed) |
| `loop.index0` | Current iteration (0-indexed) |
| `loop.first` | True if first iteration |
| `loop.last` | True if last iteration |
| `loop.length` | Total items |

---

## Filters

### Built-in Filters

```twig
{{ name|upper }}              {# JOHN #}
{{ name|lower }}              {# john #}
{{ name|title }}              {# John Doe #}
{{ name|length }}             {# 8 #}
{{ text|truncate(100) }}      {# Truncate to 100 chars #}
{{ text|escape }}             {# Escape HTML #}
{{ text|striptags }}          {# Remove HTML tags #}
{{ date|date('Y-m-d') }}      {# Format date #}
{{ json_data|json_encode }}   {# JSON encode #}
{{ number|number_format(2) }} {# Format number #}
{{ text|replace({'foo': 'bar'}) }} {# Replace text #}
```

### Custom Filters

Pype includes custom filters:

```twig
{{ name|uppercase }}          {# Same as upper #}
{{ users|limit(5) }}          {# Limit array to 5 items #}
```

### Filter Chaining

```twig
{{ name|lower|title }}
{{ text|striptags|truncate(100) }}
```

---

## Global Functions

### Helper Functions Available in Twig

```twig
{{ csrf_field() }}
{{ csrf_field|raw }}

{{ asset('css/style.css') }}
{{ url('/users') }}
{{ url('/users/profile', {id: 1}) }}

{{ env('APP_NAME') }}

{{ session('user_name') }}
{{ flash('error') }}

{{ sanitize(user_input) }}

{{ now() }}
{{ today() }}

{{ slugify('Hello World') }}

{{ excerpt(post.content, 200) }}
{{ readingTime(post.content) }}

{{ base_path('Storage/logs/app.log') }}
{{ storage_path('uploads') }}

{{ old('email') }}

{{ check() ? 'Logged in' : 'Guest' }}
```

### CSRF in Forms

```twig
<form method="POST" action="/submit">
    {{ csrf_field()|raw }}
    
    <input type="text" name="name" value="{{ old('name') }}">
    <button type="submit">Submit</button>
</form>
```

---

## Including Templates

### Include Partial

```twig
{# Include header #}
{% include "partials/header.twig" %}

{# Include with variables #}
{% include "partials/card.twig" with {title: 'Card Title', body: 'Content'} %}
```

### Include with Variables

```twig
{% set data = {users: users, title: 'User List'} %}
{% include "partials/user-list.twig" with data %}
```

---

## Macros

Reusable template functions:

```twig
{# Resources/views/macros/forms.twig #}
{% macro input(name, type, value, label) %}
    <div class="form-group">
        <label>{{ label }}</label>
        <input type="{{ type }}" name="{{ name }}" value="{{ value }}">
    </div>
{% endmacro %}

{% macro select(name, options, selected) %}
    <select name="{{ name }}">
        {% for key, value in options %}
            <option value="{{ key }}" {% if key == selected %}selected{% endif %}>
                {{ value }}
            </option>
        {% endfor %}
    </select>
{% endmacro %}
```

Use macros:

```twig
{% import "macros/forms.twig" as forms %}

{{ forms.input('name', 'text', old('name'), 'Name') }}
{{ forms.input('email', 'email', old('email'), 'Email') }}
```

---

## Complete Example: User List

### Controller

```php
public function index()
{
    $users = \App\Models\User::all();
    
    view('users.index', [
        'users' => $users,
        'title' => 'All Users'
    ]);
}
```

### Template

```twig
{# Resources/views/users/index.twig #}
{% extends "layout.twig" %}

{% block title %}Users - My App{% endblock %}

{% block content %}
    <div class="container">
        <h1>{{ title }}</h1>
        
        {% if flash('success') %}
            <div class="alert alert-success">
                {{ flash('success') }}
            </div>
        {% endif %}
        
        {% if flash('error') %}
            <div class="alert alert-danger">
                {{ flash('error') }}
            </div>
        {% endif %}
        
        <a href="{{ url('/users/create') }}" class="btn btn-primary">Add User</a>
        
        {% if users|length > 0 %}
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {% for user in users %}
                        <tr>
                            <td>{{ loop.index }}</td>
                            <td>{{ user.name|escape }}</td>
                            <td>{{ user.email|escape }}</td>
                            <td>
                                <a href="{{ url('/users/' ~ user.id) }}">View</a>
                                <a href="{{ url('/users/' ~ user.id ~ '/edit') }}">Edit</a>
                                <form method="POST" action="{{ url('/users/' ~ user.id) }}" style="display:inline">
                                    {{ csrf_field()|raw }}
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" onclick="return confirm('Delete?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
        {% else %}
            <p>No users found.</p>
        {% endif %}
    </div>
{% endblock %}
```

---

## Security

### Auto-Escaping

Twig automatically escapes output to prevent XSS:

```twig
{{ user.name }}  {# Automatically escaped #}
```

### Disable Escaping

Only for trusted content:

```twig
{{ trusted_html|raw }}
```

### Safe Blocks

Mark blocks as safe for HTML:

```twig
{% block content is safe %}
    {{ html_content }}
{% endblock %}
```

---

## Tips

1. **Always extend a layout** for consistent page structure
2. **Use filters** for data formatting
3. **Keep templates simple** - logic belongs in controllers
4. **Use partials** to reuse components
5. **Name blocks clearly** for maintainability
6. **Escape all user input** (automatic, but be aware)
7. **Use `old()`** to preserve form data after validation errors
