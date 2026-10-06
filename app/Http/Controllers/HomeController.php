<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Slider;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $sliders = Slider::where('is_active', true)->orderBy('sort')->get();
        $featured = Event::with(['category', 'hall.venue', 'ticketTypes'])->published()->upcoming()
            ->where('is_featured', true)->orderBy('starts_at')->limit(8)->get();
        $events = Event::with(['category', 'hall.venue', 'ticketTypes'])->published()->upcoming()
            ->orderBy('starts_at')->limit(12)->get();
        $categories = Category::where('is_active', true)->orderBy('sort')->get();

        return view('home', compact('sliders', 'featured', 'events', 'categories'));
    }

    public function events(Request $request)
    {
        $q = Event::with(['category', 'hall.venue', 'ticketTypes'])->published();
        if ($request->boolean('past')) {
            $q->where('starts_at', '<', now())->orderByDesc('starts_at');
        } else {
            $q->upcoming()->orderBy('starts_at');
        }
        $category = null;
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            $q->where('category_id', $category?->id);
        }
        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('venue_name', 'like', $term)->orWhere('subtitle', 'like', $term));
        }
        return view('events', [
            'events' => $q->paginate(12)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('sort')->get(),
            'category' => $category,
        ]);
    }
}
